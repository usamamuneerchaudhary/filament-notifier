<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;
use Usamamuneerchaudhary\Notifier\Services\RateLimitingService;
use Usamamuneerchaudhary\Notifier\Tests\TestCase;

class RateLimitingServiceTest extends TestCase
{
    private RateLimitingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RateLimitingService::class);
        Cache::flush();
    }

    public function test_can_send_returns_true_when_rate_limiting_disabled()
    {
        NotificationSetting::set('rate_limiting', [
            'enabled' => false,
        ], 'rate_limiting');

        $this->assertTrue($this->service->canSend());
    }

    public function test_can_send_returns_true_when_within_limits()
    {
        NotificationSetting::set('rate_limiting', [
            'enabled' => true,
            'max_per_minute' => 10,
            'max_per_hour' => 100,
            'max_per_day' => 1000,
        ], 'rate_limiting');

        $this->assertTrue($this->service->canSend());
    }

    public function test_can_send_returns_false_when_minute_limit_exceeded()
    {
        NotificationSetting::set('rate_limiting', [
            'enabled' => true,
            'max_per_minute' => 5,
            'max_per_hour' => 100,
            'max_per_day' => 1000,
        ], 'rate_limiting');

        // Set minute counter to limit
        $now = now();
        $minuteKey = 'notifier:rate_limit:minute:'.$now->format('Y-m-d-H-i');
        Cache::put($minuteKey, 5, now()->addMinutes(2));

        $this->assertFalse($this->service->canSend());
    }

    public function test_can_send_returns_false_when_hour_limit_exceeded()
    {
        NotificationSetting::set('rate_limiting', [
            'enabled' => true,
            'max_per_minute' => 100,
            'max_per_hour' => 10,
            'max_per_day' => 1000,
        ], 'rate_limiting');

        // Set hour counter to limit
        $now = now();
        $hourKey = 'notifier:rate_limit:hour:'.$now->format('Y-m-d-H');
        Cache::put($hourKey, 10, now()->addHours(2));

        $this->assertFalse($this->service->canSend());
    }

    public function test_can_send_returns_false_when_day_limit_exceeded()
    {
        NotificationSetting::set('rate_limiting', [
            'enabled' => true,
            'max_per_minute' => 100,
            'max_per_hour' => 1000,
            'max_per_day' => 10,
        ], 'rate_limiting');

        // Set day counter to limit
        $now = now();
        $dayKey = 'notifier:rate_limit:day:'.$now->format('Y-m-d');
        Cache::put($dayKey, 10, now()->addDays(2));

        $this->assertFalse($this->service->canSend());
    }

    public function test_increment_updates_all_counters()
    {
        $now = now();
        $minuteKey = 'notifier:rate_limit:minute:'.$now->format('Y-m-d-H-i');
        $hourKey = 'notifier:rate_limit:hour:'.$now->format('Y-m-d-H');
        $dayKey = 'notifier:rate_limit:day:'.$now->format('Y-m-d');

        $this->assertEquals(0, Cache::get($minuteKey, 0));
        $this->assertEquals(0, Cache::get($hourKey, 0));
        $this->assertEquals(0, Cache::get($dayKey, 0));

        $this->service->increment();

        $this->assertEquals(1, Cache::get($minuteKey, 0));
        $this->assertEquals(1, Cache::get($hourKey, 0));
        $this->assertEquals(1, Cache::get($dayKey, 0));

        $this->service->increment();

        $this->assertEquals(2, Cache::get($minuteKey, 0));
        $this->assertEquals(2, Cache::get($hourKey, 0));
        $this->assertEquals(2, Cache::get($dayKey, 0));
    }

    public function test_get_current_count_returns_correct_value()
    {
        $now = now();
        $minuteKey = 'notifier:rate_limit:minute:'.$now->format('Y-m-d-H-i');
        Cache::put($minuteKey, 5, now()->addMinutes(2));

        $this->assertEquals(5, $this->service->getCurrentCount('minute'));
    }

    public function test_get_status_returns_complete_information()
    {
        NotificationSetting::set('rate_limiting', [
            'enabled' => true,
            'max_per_minute' => 60,
            'max_per_hour' => 1000,
            'max_per_day' => 10000,
        ], 'rate_limiting');

        $status = $this->service->getStatus();

        $this->assertTrue($status['enabled']);
        $this->assertArrayHasKey('limits', $status);
        $this->assertArrayHasKey('minute', $status['limits']);
        $this->assertArrayHasKey('hour', $status['limits']);
        $this->assertArrayHasKey('day', $status['limits']);

        foreach (['minute', 'hour', 'day'] as $period) {
            $this->assertArrayHasKey('max', $status['limits'][$period]);
            $this->assertArrayHasKey('current', $status['limits'][$period]);
            $this->assertIsInt($status['limits'][$period]['max']);
            $this->assertIsInt($status['limits'][$period]['current']);
        }
    }
}
