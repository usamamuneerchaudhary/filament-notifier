<?php

namespace Usamamuneerchaudhary\Notifier\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;
use Usamamuneerchaudhary\Notifier\Events\NotificationStatusUpdated;
use Usamamuneerchaudhary\Notifier\Exceptions\DeliveryFailedException;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Services\ChannelDriverFactory;
use Usamamuneerchaudhary\Notifier\Services\ChannelDrivers\ProvidesDeliveryReference;
use Usamamuneerchaudhary\Notifier\Services\NotifierManager;
use Usamamuneerchaudhary\Notifier\Services\RateLimitingService;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 30;

    public function __construct(
        protected int $notificationId,
        protected bool $respectRateLimit = false
    ) {
        $this->tries = (int) config('notifier.defaults.retries', 3);
        $this->timeout = (int) config('notifier.defaults.timeout', 30);
    }

    /**
     * Seconds to wait before each retry.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        $backoff = array_values(array_filter(
            (array) config('notifier.defaults.backoff', [10, 60, 300]),
            fn ($seconds): bool => is_numeric($seconds)
        ));

        return array_map('intval', $backoff) ?: [10, 60, 300];
    }

    /**
     * Deferred (rate limited) jobs may be released many times while they wait for a free slot.
     */
    public function retryUntil(): ?\DateTimeInterface
    {
        return $this->respectRateLimit ? now()->addDay() : null;
    }

    public function handle(): void
    {
        $notification = Notification::find($this->notificationId);

        if (! $notification) {
            Log::error("Notification not found: {$this->notificationId}");

            return;
        }

        if (in_array($notification->status, NotificationStatus::dispatchedValues(), true)) {
            return;
        }

        if ($this->waitForRateLimit($notification)) {
            return;
        }

        $driver = (new ChannelDriverFactory)->create($notification->channel);

        if (! $driver) {
            $this->markAsFailed($notification, "No driver found for channel: {$notification->channel}");

            return;
        }

        $this->updateStatus($notification, NotificationStatus::Sending, [
            'attempts' => $notification->attempts + 1,
            'next_retry_at' => null,
        ]);

        try {
            if (! $driver->send($notification)) {
                throw new DeliveryFailedException('Driver failed to send notification');
            }

            $this->updateStatus($notification, NotificationStatus::Sent, [
                'sent_at' => now(),
                'error' => null,
                'provider_message_id' => $driver instanceof ProvidesDeliveryReference ? $driver->deliveryReference() : null,
            ]);
        } catch (\Throwable $e) {
            if ($this->job !== null && $notification->attempts >= $this->tries) {
                $this->fail($e);

                return;
            }

            $this->markAsRetrying($notification, $e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification) {
            $this->markAsFailed($notification, $exception->getMessage());
        }
    }

    /**
     * When this job was deferred by rate limiting, reserve a slot or go back to the queue.
     */
    protected function waitForRateLimit(Notification $notification): bool
    {
        if (! $this->respectRateLimit || $notification->attempts > 0) {
            return false;
        }

        $wait = app(RateLimitingService::class)->attempt($notification->user_id, $notification->channel);

        if ($wait === null) {
            return false;
        }

        $delay = $wait + random_int(0, 5);
        $notification->update(['next_retry_at' => now()->addSeconds($delay)]);

        if ($this->job !== null) {
            $this->release($delay);

            return true;
        }

        return false;
    }

    protected function markAsRetrying(Notification $notification, string $error): void
    {
        $backoff = $this->backoff();
        $delay = $backoff[min($notification->attempts, count($backoff)) - 1] ?? end($backoff);

        $this->updateStatus($notification, NotificationStatus::Pending, [
            'error' => $error,
            'next_retry_at' => now()->addSeconds((int) $delay),
        ]);
    }

    protected function markAsFailed(Notification $notification, string $error): void
    {
        $this->updateStatus($notification, NotificationStatus::Failed, [
            'error' => $error,
            'next_retry_at' => null,
        ]);

        app(NotifierManager::class)->dispatchFallback($notification);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function updateStatus(Notification $notification, NotificationStatus $status, array $attributes = []): void
    {
        $previous = $notification->statusEnum();

        $notification->update(array_merge($attributes, ['status' => $status->value]));

        if ($previous !== $status) {
            NotificationStatusUpdated::dispatch($notification, $previous, $status);
        }
    }
}
