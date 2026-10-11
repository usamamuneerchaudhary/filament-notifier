<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Usamamuneerchaudhary\Notifier\Jobs\SendNotificationJob;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;
use Usamamuneerchaudhary\Notifier\Models\NotificationTemplate;
use Usamamuneerchaudhary\Notifier\Services\NotifierManager;
use Usamamuneerchaudhary\Notifier\Tests\TestCase;

class NotifierManagerTest extends TestCase
{
    private NotifierManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app('notifier');
    }

    public function test_it_can_send_notification_with_valid_event_and_template()
    {
        Queue::fake();

        // Create test data
        $user = $this->createUser();
        $channel = $this->createChannel('email');
        $event = $this->createEvent('user.registered');
        $template = $this->createTemplate('welcome-email', $event->key);

        // Send notification
        $this->manager->send($user, 'user.registered', [
            'name' => 'John Doe',
            'app_name' => 'Test App',
        ]);

        // Assert notification was created
        $this->assertDatabaseHas('notifier_notifications', [
            'user_id' => $user->id,
            'channel' => 'email',
            'status' => 'pending',
        ]);
    }

    public function test_it_can_schedule_notification_for_later()
    {
        Queue::fake();

        $user = $this->createUser();
        $channel = $this->createChannel('email');
        $event = $this->createEvent('reminder.email');
        $template = $this->createTemplate('reminder-email', $event->key);

        $scheduledAt = Carbon::now()->addDays(7);

        $this->manager->schedule($user, 'reminder.email', $scheduledAt, [
            'task_name' => 'Complete project review',
        ]);

        // Assert notification was created with scheduled time
        $this->assertDatabaseHas('notifier_notifications', [
            'user_id' => $user->id,
            'channel' => 'email',
            'status' => 'pending',
        ]);

        // Assert job was queued
        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_it_respects_user_preferences()
    {
        $user = $this->createUser();
        $channel = $this->createChannel('email');
        $event = $this->createEvent('user.registered');
        $template = $this->createTemplate('welcome-email', $event->key);

        // Mock user preferences to disable email
        $this->manager->registerEvent('user.registered', [
            'channels' => ['email', 'slack'],
            'template' => 'welcome-email',
        ]);

        $this->manager->send($user, 'user.registered', []);

        // Should only create one notification (for slack, since email is disabled in preferences)
        $this->assertDatabaseCount('notifier_notifications', 1);
    }

    public function test_it_handles_missing_event_configuration()
    {
        $user = $this->createUser();

        // Try to send notification for non-existent event
        $this->manager->send($user, 'non.existent.event', []);

        // Should not create any notifications
        $this->assertDatabaseCount('notifier_notifications', 0);
    }

    public function test_it_handles_missing_template()
    {
        $user = $this->createUser();
        $channel = $this->createChannel('email');
        $event = $this->createEvent('user.registered');

        // Try to send notification with non-existent template
        $this->manager->send($user, 'user.registered', []);

        // Should not create any notifications
        $this->assertDatabaseCount('notifier_notifications', 0);
    }

    public function test_it_renders_template_with_variables()
    {
        Queue::fake();

        $user = $this->createUser();
        $channel = $this->createChannel('email');
        $event = $this->createEvent('user.registered');
        $template = $this->createTemplate('welcome-email', $event->key);

        $this->manager->send($user, 'user.registered', [
            'name' => 'John Doe',
            'app_name' => 'Test App',
        ]);

        $notification = Notification::first();

        $this->assertEquals('Welcome to Test App, John Doe!', $notification->subject);
        $this->assertStringContainsString('Hi John Doe', $notification->content);
        $this->assertStringContainsString('Test App', $notification->content);
    }

    public function test_it_gets_registered_channels_and_events()
    {
        $this->manager->registerChannel('custom', new \stdClass);
        $this->manager->registerEvent('custom.event', ['channels' => ['email']]);

        $this->assertContains('custom', $this->manager->getRegisteredChannels());
        $this->assertContains('custom.event', $this->manager->getRegisteredEvents());
    }

    public function test_it_generates_tracking_token_for_notifications()
    {
        Queue::fake();

        $user = $this->createUser();
        $channel = $this->createChannel('email');
        $event = $this->createEvent('user.registered');
        $template = $this->createTemplate('welcome-email', $event->key);

        $this->manager->send($user, 'user.registered', [
            'name' => 'John Doe',
        ]);

        $notification = Notification::first();

        $this->assertNotNull($notification->data['tracking_token']);
        $this->assertEquals(32, strlen($notification->data['tracking_token']));
    }

    public function test_it_rewrites_urls_for_click_tracking()
    {
        Queue::fake();

        NotificationSetting::set('analytics', [
            'enabled' => true,
            'track_clicks' => true,
        ], 'analytics');

        $user = $this->createUser();
        $channel = $this->createChannel('email');
        $event = $this->createEvent('user.registered');

        $template = NotificationTemplate::create([
            'name' => 'test-template',
            'event_key' => $event->key,
            'subject' => 'Test',
            'content' => 'Visit <a href="https://example.com">our website</a> for more info.',
        ]);

        $this->manager->send($user, 'user.registered', []);

        $notification = Notification::first();
        $token = $notification->data['tracking_token'];
        $appUrl = rtrim(config('app.url', ''), '/');

        $this->assertStringContainsString("/notifier/track/click/{$token}", $notification->content);
        $this->assertStringContainsString('url=', $notification->content);
    }

    public function test_it_does_not_rewrite_urls_when_track_clicks_disabled()
    {
        Queue::fake();

        NotificationSetting::set('analytics', [
            'enabled' => true,
            'track_clicks' => false,
        ], 'analytics');

        $user = $this->createUser();
        $channel = $this->createChannel('email');
        $event = $this->createEvent('user.registered');

        $template = NotificationTemplate::create([
            'name' => 'test-template',
            'event_key' => $event->key,
            'subject' => 'Test',
            'content' => 'Visit <a href="https://example.com">our website</a> for more info.',
        ]);

        $this->manager->send($user, 'user.registered', []);

        $notification = Notification::first();

        $this->assertStringNotContainsString('/notifier/track/click/', $notification->content);
        $this->assertStringContainsString('https://example.com', $notification->content);
    }

    private function createUser()
    {
        return new class
        {
            public $id = 1;

            public $email = 'test@example.com';

            public $name = 'Test User';
        };
    }

    private function createChannel(string $type): NotificationChannel
    {
        return NotificationChannel::create([
            'title' => ucfirst($type).' Notifications',
            'type' => $type,
            'is_active' => true,
            'settings' => [],
        ]);
    }

    private function createTemplate(string $name, string $eventKey): NotificationTemplate
    {
        return NotificationTemplate::create([
            'name' => $name,
            'event_key' => $eventKey,
            'subject' => 'Welcome to {{app_name}}, {{name}}!',
            'content' => 'Hi {{name}},\n\nWelcome to {{app_name}}! We\'re excited to have you on board.',
            'variables' => [
                'name' => 'User\'s full name',
                'app_name' => 'Application name',
            ],
        ]);
    }

    private function createEvent(string $key): NotificationEvent
    {
        return NotificationEvent::create([
            'group' => 'User Management',
            'name' => 'User Registered',
            'key' => $key,
            'description' => 'Triggered when a new user registers',
            'is_active' => true,
        ]);
    }
}
