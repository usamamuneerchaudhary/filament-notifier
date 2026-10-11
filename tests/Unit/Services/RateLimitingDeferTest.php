<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;
use Usamamuneerchaudhary\Notifier\Services\RateLimitingService;
use Usamamuneerchaudhary\Notifier\Tests\UserTestCase;

class RateLimitingDeferTest extends UserTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_attempt_returns_seconds_when_a_per_user_limit_is_hit(): void
    {
        NotificationSetting::set('rate_limiting', [
            'enabled' => true,
            'max_per_minute' => 100,
            'max_per_hour' => 1000,
            'max_per_day' => 10000,
            'max_per_user_per_hour' => 1,
        ], 'rate_limiting');

        $service = app(RateLimitingService::class);

        $this->assertNull($service->attempt(7, 'email'));
        $this->assertIsInt($service->attempt(7, 'email'));
        $this->assertNull($service->attempt(8, 'email'));
    }

    public function test_defer_mode_still_creates_the_notification(): void
    {
        Queue::fake();

        NotificationSetting::set('rate_limiting', [
            'enabled' => true,
            'mode' => RateLimitingService::MODE_DEFER,
            'max_per_minute' => 1,
            'max_per_hour' => 100,
            'max_per_day' => 1000,
        ], 'rate_limiting');

        $user = $this->makeUser();
        $this->makeChannel('email');
        $event = $this->makeEvent('test.event', ['channels' => ['email']]);
        $this->makeTemplate($event);

        app('notifier')->send($user, 'test.event');
        app('notifier')->send($user, 'test.event');

        $this->assertDatabaseCount('notifier_notifications', 2);
    }
}
