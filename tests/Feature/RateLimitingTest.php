<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;
use Usamamuneerchaudhary\Notifier\Models\NotificationTemplate;
use Usamamuneerchaudhary\Notifier\Services\NotifierManager;
use Usamamuneerchaudhary\Notifier\Services\RateLimitingService;
use Usamamuneerchaudhary\Notifier\Tests\TestCase;

class RateLimitingTest extends TestCase
{
    private NotifierManager $manager;

    private RateLimitingService $rateLimitingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app('notifier');
        $this->rateLimitingService = app(RateLimitingService::class);

        // Enable rate limiting for tests
        NotificationSetting::set('rate_limiting', [
            'enabled' => true,
            'max_per_minute' => 5,
            'max_per_hour' => 10,
            'max_per_day' => 20,
        ], 'rate_limiting');

        // Clear cache before each test
        Cache::flush();
    }

    public function test_rate_limiting_allows_notifications_within_limits()
    {
        Queue::fake();

        $user = $this->createUser();
        $this->setupTestData();

        // Send 3 notifications (within limit of 5 per minute)
        for ($i = 0; $i < 3; $i++) {
            $this->manager->send($user, 'test.event', []);
        }

        $this->assertDatabaseCount('notifier_notifications', 3);
    }

    public function test_rate_limiting_blocks_notifications_when_minute_limit_exceeded()
    {
        Queue::fake();

        $user = $this->createUser();
        $this->setupTestData();

        // Send 5 notifications (at limit)
        for ($i = 0; $i < 5; $i++) {
            $this->manager->send($user, 'test.event', []);
        }

        // 6th notification should be blocked
        $this->manager->send($user, 'test.event', []);

        // Should only have 5 notifications
        $this->assertDatabaseCount('notifier_notifications', 5);
    }

    public function test_rate_limiting_blocks_notifications_when_hour_limit_exceeded()
    {
        Queue::fake();

        $user = $this->createUser();
        $this->setupTestData();

        // Manually set hour counter to limit using expected key format
        $now = now();
        $hourKey = 'notifier:rate_limit:hour:'.$now->format('Y-m-d-H');
        Cache::put($hourKey, 10, now()->addHours(2));

        // Try to send notification
        $this->manager->send($user, 'test.event', []);

        // Should be blocked
        $this->assertDatabaseCount('notifier_notifications', 0);
    }

    public function test_rate_limiting_blocks_notifications_when_day_limit_exceeded()
    {
        Queue::fake();

        $user = $this->createUser();
        $this->setupTestData();

        // Manually set day counter to limit using expected key format
        $now = now();
        $dayKey = 'notifier:rate_limit:day:'.$now->format('Y-m-d');
        Cache::put($dayKey, 20, now()->addDays(2));

        // Try to send notification
        $this->manager->send($user, 'test.event', []);

        // Should be blocked
        $this->assertDatabaseCount('notifier_notifications', 0);
    }

    public function test_rate_limiting_increments_counters()
    {
        Queue::fake();

        $user = $this->createUser();
        $this->setupTestData();

        $now = now();
        $minuteKey = 'notifier:rate_limit:minute:'.$now->format('Y-m-d-H-i');
        $hourKey = 'notifier:rate_limit:hour:'.$now->format('Y-m-d-H');
        $dayKey = 'notifier:rate_limit:day:'.$now->format('Y-m-d');

        $this->assertEquals(0, Cache::get($minuteKey, 0));
        $this->assertEquals(0, Cache::get($hourKey, 0));
        $this->assertEquals(0, Cache::get($dayKey, 0));

        $this->manager->send($user, 'test.event', []);

        $this->assertEquals(1, Cache::get($minuteKey, 0));
        $this->assertEquals(1, Cache::get($hourKey, 0));
        $this->assertEquals(1, Cache::get($dayKey, 0));
    }

    public function test_rate_limiting_respects_disabled_setting()
    {
        Queue::fake();

        NotificationSetting::set('rate_limiting', [
            'enabled' => false,
            'max_per_minute' => 1,
            'max_per_hour' => 1,
            'max_per_day' => 1,
        ], 'rate_limiting');

        $user = $this->createUser();
        $this->setupTestData();

        // Should be able to send even though limits are set to 1
        $this->manager->send($user, 'test.event', []);
        $this->manager->send($user, 'test.event', []);

        $this->assertDatabaseCount('notifier_notifications', 2);
    }

    public function test_rate_limiting_service_can_send_returns_true_when_disabled()
    {
        NotificationSetting::set('rate_limiting', [
            'enabled' => false,
        ], 'rate_limiting');

        $this->assertTrue($this->rateLimitingService->canSend());
    }

    public function test_rate_limiting_service_get_status_returns_correct_data()
    {
        $status = $this->rateLimitingService->getStatus();

        $this->assertTrue($status['enabled']);
        $this->assertEquals(5, $status['limits']['minute']['max']);
        $this->assertEquals(10, $status['limits']['hour']['max']);
        $this->assertEquals(20, $status['limits']['day']['max']);
        $this->assertIsInt($status['limits']['minute']['current']);
        $this->assertIsInt($status['limits']['hour']['current']);
        $this->assertIsInt($status['limits']['day']['current']);
        $this->assertGreaterThanOrEqual(0, $status['limits']['minute']['current']);
        $this->assertGreaterThanOrEqual(0, $status['limits']['hour']['current']);
        $this->assertGreaterThanOrEqual(0, $status['limits']['day']['current']);
    }

    private function setupTestData(): void
    {
        NotificationChannel::create([
            'title' => 'Email',
            'type' => 'email',
            'is_active' => true,
        ]);

        $event = NotificationEvent::create([
            'group' => 'Test',
            'name' => 'Test Event',
            'key' => 'test.event',
            'is_active' => true,
            'settings' => ['channels' => ['email']],
        ]);

        NotificationTemplate::create([
            'name' => 'test-template',
            'event_key' => $event->key,
            'subject' => 'Test',
            'content' => 'Test',
        ]);
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
}
