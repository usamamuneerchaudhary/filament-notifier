<?php

namespace Usamamuneerchaudhary\Notifier\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;
use Usamamuneerchaudhary\Notifier\Jobs\SendNotificationJob;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;
use Usamamuneerchaudhary\Notifier\Models\NotificationTemplate;

class NotifierManager
{
    protected array $channels = [];

    protected array $events = [];

    public function registerChannel(string $type, $handler): void
    {
        $this->channels[$type] = $handler;
    }

    public function registerEvent(string $key, array $config): void
    {
        $this->events[$key] = $config;
    }

    /**
     * Send the event to every channel configured for it, honouring the user's preferences.
     */
    public function send($user, string $eventKey, array $data = []): void
    {
        $this->deliverToChannels($user, $eventKey, $data);
    }

    /**
     * Same as send(), but delivers synchronously instead of queueing.
     */
    public function sendNow($user, string $eventKey, array $data = []): void
    {
        $this->deliverToChannels($user, $eventKey, $data, synchronous: true);
    }

    /**
     * Send the event through a single channel (still honouring the user's preferences).
     */
    public function sendToChannel($user, string $eventKey, string $channelType, array $data = []): void
    {
        try {
            $eventConfig = $this->getEventConfig($eventKey);
            if (! $eventConfig) {
                Log::warning("Event configuration not found for: {$eventKey}");

                return;
            }

            $template = $this->getTemplate($eventConfig['template'] ?? null);
            if (! $template) {
                Log::warning("Template not found for event: {$eventKey}");

                return;
            }

            $preferences = app(PreferenceService::class)->getUserPreferences($user, $eventKey);
            if (! app(PreferenceService::class)->shouldSendToChannel($user, $channelType, $preferences)) {
                Log::info("Channel {$channelType} is not available, active or enabled for the user");

                return;
            }

            $this->createNotification($user, $template, $channelType, $data, $eventKey);
        } catch (\Exception $e) {
            Log::error("Failed to send notification to channel {$channelType} for event {$eventKey}: ".$e->getMessage());
        }
    }

    /**
     * Schedule the event for later. Each channel is queued once, delayed until $scheduledAt.
     */
    public function schedule($user, string $eventKey, Carbon $scheduledAt, array $data = []): void
    {
        $this->deliverToChannels($user, $eventKey, $data, scheduledAt: $scheduledAt);
    }

    /**
     * Queue a failed notification again.
     */
    public function retryNotification(Notification $notification): void
    {
        $notification->update([
            'status' => NotificationStatus::Pending->value,
            'error' => null,
            'attempts' => 0,
            'next_retry_at' => null,
        ]);

        $this->dispatchJob($notification, false, null);
    }

    /**
     * Create and queue a notification on the event's fallback channel after a permanent failure.
     */
    public function dispatchFallback(Notification $failed): ?Notification
    {
        try {
            if ($failed->isFallback() || ! $failed->event_key) {
                return null;
            }

            if (Notification::where('fallback_for_notification_id', $failed->id)->exists()) {
                return null;
            }

            $fallbackChannel = $this->fallbackChannelFor($failed->event_key, $failed->channel);
            if (! $fallbackChannel) {
                return null;
            }

            $user = $failed->user;
            $template = $failed->template;
            if (! $user || ! $template) {
                return null;
            }

            $preferenceService = app(PreferenceService::class);
            $preferences = $preferenceService->getUserPreferences($user, $failed->event_key);
            if (! $preferenceService->shouldSendToChannel($user, $fallbackChannel, $preferences)) {
                return null;
            }

            $data = $failed->data ?? [];
            unset($data['tracking_token']);

            return $this->createNotification($user, $template, $fallbackChannel, $data, $failed->event_key, fallbackFor: $failed->id);
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch fallback for notification {$failed->id}: ".$e->getMessage());

            return null;
        }
    }

    protected function deliverToChannels($user, string $eventKey, array $data, bool $synchronous = false, ?Carbon $scheduledAt = null): void
    {
        try {
            $eventConfig = $this->getEventConfig($eventKey);
            if (! $eventConfig) {
                Log::warning("Event configuration not found for: {$eventKey}");

                return;
            }

            $template = $this->getTemplate($eventConfig['template'] ?? null);
            if (! $template) {
                Log::warning("Template not found for event: {$eventKey}");

                return;
            }

            $preferenceService = app(PreferenceService::class);
            $preferences = $preferenceService->getUserPreferences($user, $eventKey);

            foreach ($eventConfig['channels'] ?? [] as $channelType) {
                if (! $preferenceService->shouldSendToChannel($user, $channelType, $preferences)) {
                    continue;
                }

                $this->createNotification($user, $template, $channelType, $data, $eventKey, $synchronous, $scheduledAt);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send notification for event {$eventKey}: ".$e->getMessage());
        }
    }

    protected function getEventConfig(string $eventKey): ?array
    {
        if (isset($this->events[$eventKey])) {
            return $this->events[$eventKey];
        }

        $event = NotificationEvent::where('key', $eventKey)->first();
        if ($event) {
            $template = $event->templates()->first();
            if ($template) {
                return [
                    'channels' => $event->channelList() ?: ['email'],
                    'template' => $template,
                ];
            }
        }

        return null;
    }

    protected function fallbackChannelFor(string $eventKey, string $channel): ?string
    {
        $configured = $this->events[$eventKey]['fallbacks'][$channel] ?? null;
        if (is_string($configured) && $configured !== '' && $configured !== $channel) {
            return $configured;
        }

        return NotificationEvent::where('key', $eventKey)->first()?->fallbackChannelFor($channel);
    }

    protected function getTemplate($template): ?NotificationTemplate
    {
        if (! $template) {
            return null;
        }

        if ($template instanceof NotificationTemplate) {
            return $template;
        }

        return NotificationTemplate::where('name', $template)->first();
    }

    protected function createNotification(
        $user,
        NotificationTemplate $template,
        string $channelType,
        array $data,
        string $eventKey,
        bool $synchronous = false,
        ?Carbon $scheduledAt = null,
        ?int $fallbackFor = null,
    ): ?Notification {
        $rateLimitingService = app(RateLimitingService::class);
        $userId = $user->id ?? null;
        $retryAfter = $rateLimitingService->attempt($userId, $channelType);
        $isRateLimited = $retryAfter !== null;

        if ($isRateLimited && $rateLimitingService->mode() !== RateLimitingService::MODE_DEFER) {
            Log::warning('Notification creation blocked due to rate limit', [
                'user_id' => $userId,
                'event_key' => $eventKey,
                'channel' => $channelType,
            ]);

            return null;
        }

        // Generate tracking token for analytics
        $trackingToken = Str::random(32);

        $dataWithUser = array_merge($data, ['user' => $user, 'tracking_token' => $trackingToken]);

        if ($channelType === 'email' && ! isset($data['unsubscribe_url']) && $userId !== null) {
            $unsubscribeUrl = app(UnsubscribeService::class)->urlFor($user, $eventKey);
            if ($unsubscribeUrl) {
                $dataWithUser['unsubscribe_url'] = $unsubscribeUrl;
            }
        }

        $renderedContent = app(TemplateRenderingService::class)->render($template, $dataWithUser, $channelType);

        $notification = Notification::create([
            'notification_template_id' => $template->id,
            'user_id' => $userId,
            'channel' => $channelType,
            'event_key' => $eventKey,
            'subject' => $renderedContent['subject'] ?? '',
            'content' => $renderedContent['content'] ?? '',
            'data' => array_merge($data, ['tracking_token' => $trackingToken]),
            'tracking_token' => $trackingToken,
            'scheduled_at' => $scheduledAt,
            'status' => NotificationStatus::Pending->value,
            'fallback_for_notification_id' => $fallbackFor,
        ]);

        Cache::put(
            "notifier:tracking_token:{$trackingToken}",
            $notification->id,
            now()->addDays(30)
        );

        $delay = $scheduledAt ? max(0, (int) now()->diffInSeconds($scheduledAt, false)) : 0;
        if ($isRateLimited) {
            $delay = max($delay, $retryAfter + random_int(0, 5));
        }

        $this->dispatchJob($notification, $isRateLimited, $delay > 0 ? $delay : null, $synchronous && $delay === 0);

        return $notification;
    }

    protected function dispatchJob(Notification $notification, bool $respectRateLimit, ?int $delaySeconds, bool $synchronous = false): void
    {
        $job = new SendNotificationJob($notification->id, $respectRateLimit);

        $queue = config('notifier.defaults.queue');
        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        if ($synchronous) {
            try {
                SendNotificationJob::dispatchSync($notification->id);
            } catch (\Throwable $e) {
                (new SendNotificationJob($notification->id))->failed($e);
            }

            return;
        }

        $pending = dispatch($job);

        if ($delaySeconds !== null) {
            $pending->delay(now()->addSeconds($delaySeconds));
        }
    }

    public function getRegisteredChannels(): array
    {
        return array_keys($this->channels);
    }

    public function getRegisteredEvents(): array
    {
        return array_keys($this->events);
    }
}
