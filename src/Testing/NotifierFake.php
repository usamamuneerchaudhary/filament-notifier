<?php

namespace Usamamuneerchaudhary\Notifier\Testing;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;
use Usamamuneerchaudhary\Notifier\Services\NotifierManager;

/**
 * Drop-in replacement for the notifier that records sends instead of delivering them.
 *
 * Use `Notifier::fake()` in a test, run your code, then assert with `assertSent()` and friends.
 */
class NotifierFake extends NotifierManager
{
    /**
     * @var array<int, array{user: mixed, event: string, data: array<string, mixed>, channel: string|null, scheduled_at: Carbon|null, immediate: bool}>
     */
    protected array $sent = [];

    public function send($user, string $eventKey, array $data = []): void
    {
        $this->record($user, $eventKey, $data);
    }

    public function sendNow($user, string $eventKey, array $data = []): void
    {
        $this->record($user, $eventKey, $data, immediate: true);
    }

    public function sendToChannel($user, string $eventKey, string $channelType, array $data = []): void
    {
        $this->record($user, $eventKey, $data, channel: $channelType);
    }

    public function schedule($user, string $eventKey, Carbon $scheduledAt, array $data = []): void
    {
        $this->record($user, $eventKey, $data, scheduledAt: $scheduledAt);
    }

    /**
     * Assert the event was sent. Pass a count, or a closure receiving ($user, $data, $record) that returns true for a match.
     */
    public function assertSent(string $eventKey, Closure|int|null $callback = null): void
    {
        if (is_int($callback)) {
            $this->assertSentTimes($eventKey, $callback);

            return;
        }

        PHPUnit::assertTrue(
            $this->matching($eventKey, $callback)->isNotEmpty(),
            "The expected [{$eventKey}] notification was not sent."
        );
    }

    public function assertSentTimes(string $eventKey, int $times = 1): void
    {
        $count = $this->matching($eventKey)->count();

        PHPUnit::assertSame(
            $times,
            $count,
            "The notification [{$eventKey}] was sent {$count} times instead of {$times} times."
        );
    }

    public function assertNotSent(string $eventKey, ?Closure $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->matching($eventKey, $callback)->isEmpty(),
            "The unexpected [{$eventKey}] notification was sent."
        );
    }

    public function assertSentTo($user, string $eventKey, ?Closure $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->matching($eventKey, $callback)->contains(fn (array $record): bool => $this->isSameUser($record['user'], $user)),
            "The expected [{$eventKey}] notification was not sent to the given user."
        );
    }

    public function assertNotSentTo($user, string $eventKey, ?Closure $callback = null): void
    {
        PHPUnit::assertFalse(
            $this->matching($eventKey, $callback)->contains(fn (array $record): bool => $this->isSameUser($record['user'], $user)),
            "The unexpected [{$eventKey}] notification was sent to the given user."
        );
    }

    public function assertSentToChannel(string $eventKey, string $channel): void
    {
        PHPUnit::assertTrue(
            $this->matching($eventKey)->contains(fn (array $record): bool => $record['channel'] === $channel),
            "The expected [{$eventKey}] notification was not sent to the [{$channel}] channel."
        );
    }

    public function assertScheduled(string $eventKey, ?Closure $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->matching($eventKey, $callback)->contains(fn (array $record): bool => $record['scheduled_at'] !== null),
            "The expected [{$eventKey}] notification was not scheduled."
        );
    }

    public function assertSentCount(int $expected): void
    {
        PHPUnit::assertCount($expected, $this->sent, "Expected {$expected} notifications to be sent, but ".count($this->sent).' were.');
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->sent, 'Notifications were sent unexpectedly: '.implode(', ', array_column($this->sent, 'event')));
    }

    /**
     * Everything recorded so far, optionally for one event.
     *
     * @return array<int, array{user: mixed, event: string, data: array<string, mixed>, channel: string|null, scheduled_at: Carbon|null, immediate: bool}>
     */
    public function sent(?string $eventKey = null): array
    {
        return $eventKey === null
            ? $this->sent
            : array_values(array_filter($this->sent, fn (array $record): bool => $record['event'] === $eventKey));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function record($user, string $eventKey, array $data, ?string $channel = null, ?Carbon $scheduledAt = null, bool $immediate = false): void
    {
        $this->sent[] = [
            'user' => $user,
            'event' => $eventKey,
            'data' => $data,
            'channel' => $channel,
            'scheduled_at' => $scheduledAt,
            'immediate' => $immediate,
        ];
    }

    protected function matching(string $eventKey, ?Closure $callback = null): Collection
    {
        return collect($this->sent($eventKey))->filter(
            fn (array $record): bool => $callback === null || (bool) $callback($record['user'], $record['data'], $record)
        );
    }

    protected function isSameUser($recorded, $expected): bool
    {
        if ($recorded === $expected) {
            return true;
        }

        $recordedId = is_object($recorded) ? ($recorded->id ?? null) : null;
        $expectedId = is_object($expected) ? ($expected->id ?? null) : null;

        return $recordedId !== null && $recordedId === $expectedId;
    }
}
