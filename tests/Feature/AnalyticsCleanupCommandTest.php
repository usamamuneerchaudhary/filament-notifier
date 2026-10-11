<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;
use Usamamuneerchaudhary\Notifier\Models\NotificationTemplate;
use Usamamuneerchaudhary\Notifier\Tests\TestCase;

class AnalyticsCleanupCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Setup test data
        $channel = NotificationChannel::create([
            'title' => 'Email',
            'type' => 'email',
            'is_active' => true,
        ]);

        $event = NotificationEvent::create([
            'group' => 'Test',
            'name' => 'Test Event',
            'key' => 'test.event',
            'is_active' => true,
        ]);

        $template = NotificationTemplate::create([
            'name' => 'test-template',
            'event_key' => $event->key,
            'subject' => 'Test',
            'content' => 'Test',
        ]);
    }

    public function test_cleanup_command_respects_analytics_enabled_setting()
    {
        NotificationSetting::set('analytics', [
            'enabled' => false,
            'retention_days' => 30,
        ], 'analytics');

        $oldNotification = Notification::create([
            'notification_template_id' => NotificationTemplate::first()->id,
            'user_id' => 1,
            'channel' => 'email',
            'subject' => 'Test',
            'content' => 'Test',
            'status' => 'sent',
            'created_at' => Carbon::now()->subDays(35),
            'opened_at' => Carbon::now()->subDays(35),
            'opens_count' => 10,
            'clicks_count' => 5,
        ]);

        $result = Artisan::call('notifier:cleanup-analytics');

        $this->assertEquals(0, $result); // Command should succeed but do nothing

        $oldNotification->refresh();
        // Should not be cleaned when analytics is disabled
        $this->assertNotNull($oldNotification->opened_at);
        $this->assertEquals(10, $oldNotification->opens_count);
    }

    public function test_cleanup_command_dry_run_shows_preview()
    {
        NotificationSetting::set('analytics', [
            'enabled' => true,
            'retention_days' => 30,
        ], 'analytics');

        $oldNotification = Notification::create([
            'notification_template_id' => NotificationTemplate::first()->id,
            'user_id' => 1,
            'channel' => 'email',
            'subject' => 'Test',
            'content' => 'Test',
            'status' => 'sent',
            'created_at' => Carbon::now()->subDays(35),
            'opened_at' => Carbon::now()->subDays(35),
            'opens_count' => 10,
        ]);

        $result = Artisan::call('notifier:cleanup-analytics', ['--dry-run' => true]);

        $this->assertEquals(0, $result);

        $oldNotification->refresh();
        // Should not be cleaned in dry run
        $this->assertNotNull($oldNotification->opened_at);
        $this->assertEquals(10, $oldNotification->opens_count);
    }

    public function test_cleanup_command_handles_no_old_data()
    {
        NotificationSetting::set('analytics', [
            'enabled' => true,
            'retention_days' => 30,
        ], 'analytics');

        // Only create recent notification
        Notification::create([
            'notification_template_id' => NotificationTemplate::first()->id,
            'user_id' => 1,
            'channel' => 'email',
            'subject' => 'Test',
            'content' => 'Test',
            'status' => 'sent',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $result = Artisan::call('notifier:cleanup-analytics');

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('No old analytics data found', Artisan::output());
    }
}
