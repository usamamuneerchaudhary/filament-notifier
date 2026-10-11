<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Feature;

use Carbon\Carbon;
use PHPUnit\Framework\AssertionFailedError;
use Usamamuneerchaudhary\Notifier\Facades\Notifier;
use Usamamuneerchaudhary\Notifier\Tests\TestCase;

class NotifierFakeTest extends TestCase
{
    public function test_it_records_sends_without_creating_notifications(): void
    {
        $fake = Notifier::fake();
        $user = $this->user();

        Notifier::send($user, 'user.registered', ['name' => 'Jane']);

        $fake->assertSent('user.registered');
        $fake->assertSentTo($user, 'user.registered');
        $fake->assertSentCount(1);
        $this->assertDatabaseCount('notifier_notifications', 0);
    }

    public function test_it_asserts_a_matching_callback(): void
    {
        $fake = Notifier::fake();

        Notifier::send($this->user(), 'order.shipped', ['order_id' => 42]);

        $fake->assertSent('order.shipped', fn ($user, array $data): bool => $data['order_id'] === 42);
    }

    public function test_it_fails_when_the_event_was_not_sent(): void
    {
        $this->expectException(AssertionFailedError::class);

        Notifier::fake()->assertSent('never.sent');
    }

    public function test_it_asserts_nothing_was_sent(): void
    {
        Notifier::fake()->assertNothingSent();
    }

    public function test_it_asserts_the_event_was_not_sent(): void
    {
        $fake = Notifier::fake();

        Notifier::send($this->user(), 'user.registered');

        $fake->assertNotSent('order.shipped');
        $fake->assertNotSentTo((object) ['id' => 99], 'user.registered');
    }

    public function test_it_records_channel_schedule_and_immediate_sends(): void
    {
        $fake = Notifier::fake();
        $user = $this->user();
        $scheduledAt = Carbon::now()->addHour();

        Notifier::sendToChannel($user, 'user.registered', 'sms');
        Notifier::schedule($user, 'reminder.email', $scheduledAt);
        Notifier::sendNow($user, 'user.registered');

        $fake->assertSentToChannel('user.registered', 'sms');
        $fake->assertScheduled('reminder.email');
        $fake->assertSentTimes('user.registered', 2);
        $this->assertTrue($fake->sent('user.registered')[1]['immediate']);
    }

    private function user(): object
    {
        return new class
        {
            public $id = 1;

            public $email = 'test@example.com';

            public $name = 'Test User';
        };
    }
}
