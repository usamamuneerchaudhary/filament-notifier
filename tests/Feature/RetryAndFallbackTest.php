<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;
use Usamamuneerchaudhary\Notifier\Jobs\SendNotificationJob;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Services\PreferenceService;
use Usamamuneerchaudhary\Notifier\Tests\UserTestCase;

class RetryAndFallbackTest extends UserTestCase
{
    public function test_schedule_dispatches_the_job_once(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $this->makeChannel('email');
        $event = $this->makeEvent('reminder.email', ['channels' => ['email']]);
        $this->makeTemplate($event);

        app('notifier')->schedule($user, 'reminder.email', now()->addDay());

        Queue::assertPushed(SendNotificationJob::class, 1);
        $this->assertDatabaseCount('notifier_notifications', 1);
        $this->assertNotNull(Notification::first()->scheduled_at);
    }

    public function test_send_now_delivers_synchronously(): void
    {
        $user = $this->makeUser();
        $this->makeChannel('email', ['from_address' => 'noreply@example.com']);
        $event = $this->makeEvent('user.registered', ['channels' => ['email']]);
        $this->makeTemplate($event);

        app('notifier')->sendNow($user, 'user.registered', ['name' => 'Jane']);

        $notification = Notification::first();

        $this->assertNotNull($notification);
        $this->assertSame(NotificationStatus::Sent->value, $notification->status);
        $this->assertNotNull($notification->sent_at);
    }

    public function test_send_to_channel_honours_disabled_preferences(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $this->makeChannel('email');
        $event = $this->makeEvent('user.registered', ['channels' => ['email']]);
        $this->makeTemplate($event);

        app(PreferenceService::class)
            ->setChannelEnabled($user->id, $event, 'email', false);

        app('notifier')->sendToChannel($user, 'user.registered', 'email');

        $this->assertDatabaseCount('notifier_notifications', 0);
    }

    public function test_it_retries_a_failed_notification(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $event = $this->makeEvent();
        $template = $this->makeTemplate($event);

        $notification = Notification::create([
            'notification_template_id' => $template->id,
            'user_id' => $user->id,
            'channel' => 'email',
            'event_key' => $event->key,
            'subject' => 'Hello',
            'content' => 'World',
            'status' => NotificationStatus::Failed->value,
            'error' => 'Driver failed',
            'attempts' => 3,
        ]);

        app('notifier')->retryNotification($notification);

        $this->assertSame(NotificationStatus::Pending->value, $notification->fresh()->status);
        $this->assertSame(0, $notification->fresh()->attempts);
        $this->assertNull($notification->fresh()->error);
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_it_dispatches_a_fallback_channel_after_a_permanent_failure(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $this->makeChannel('email');
        $this->makeChannel('sms');
        $event = $this->makeEvent('order.shipped', [
            'channels' => ['email'],
            'fallbacks' => ['email' => 'sms'],
        ]);
        $template = $this->makeTemplate($event);

        $failed = Notification::create([
            'notification_template_id' => $template->id,
            'user_id' => $user->id,
            'channel' => 'email',
            'event_key' => $event->key,
            'subject' => 'Hello',
            'content' => 'World',
            'status' => NotificationStatus::Failed->value,
            'data' => ['name' => 'Jane'],
        ]);

        $fallback = app('notifier')->dispatchFallback($failed);

        $this->assertNotNull($fallback);
        $this->assertSame('sms', $fallback->channel);
        $this->assertSame($failed->id, $fallback->fallback_for_notification_id);
        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_the_job_uses_configured_backoff(): void
    {
        config(['notifier.defaults.backoff' => [15, 45, 90]]);

        $job = new SendNotificationJob(1);

        $this->assertSame([15, 45, 90], $job->backoff());
        $this->assertSame(3, $job->tries);
    }
}
