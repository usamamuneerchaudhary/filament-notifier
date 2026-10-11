<?php

namespace Usamamuneerchaudhary\Notifier\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;

class RateLimitingService
{
    public const MODE_DROP = 'drop';

    public const MODE_DEFER = 'defer';

    /**
     * Check if notification can be sent based on the global rate limits (read only).
     */
    public function canSend(): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        foreach ($this->globalLimits() as $period => $maxLimit) {
            if (! $this->checkLimit($period, $maxLimit)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Atomically reserve a slot for one notification.
     *
     * Returns null when the notification may be sent now (counters are incremented), or the
     * number of seconds to wait before the blocking limit resets (counters are left untouched).
     */
    public function attempt(mixed $userId = null, ?string $channel = null): ?int
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $now = now();
        $reserved = [];
        $retryAfter = null;

        foreach ($this->limitsFor($userId, $channel) as $limit) {
            $key = $this->counterKey($limit['scope'], $limit['period'], $now);
            $count = $this->incrementCounter($key, $limit['period']);
            $reserved[] = $key;

            if ($count > $limit['max']) {
                $retryAfter = max($retryAfter ?? 0, $this->secondsUntilReset($limit['period'], $now));

                Log::warning("Rate limit exceeded: {$limit['period']} limit ({$limit['max']})", [
                    'scope' => $limit['scope'],
                    'current_count' => $count - 1,
                    'limit' => $limit['max'],
                    'period' => $limit['period'],
                ]);
            }
        }

        if ($retryAfter !== null) {
            foreach ($reserved as $key) {
                Cache::decrement($key);
            }
        }

        return $retryAfter;
    }

    /**
     * Seconds until a notification could be sent, or null when it can be sent right now (read only).
     */
    public function retryAfter(mixed $userId = null, ?string $channel = null): ?int
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $now = now();
        $retryAfter = null;

        foreach ($this->limitsFor($userId, $channel) as $limit) {
            $count = (int) Cache::get($this->counterKey($limit['scope'], $limit['period'], $now), 0);

            if ($count >= $limit['max']) {
                $retryAfter = max($retryAfter ?? 0, $this->secondsUntilReset($limit['period'], $now));
            }
        }

        return $retryAfter;
    }

    /**
     * What to do with a notification that hit a limit: 'drop' or 'defer'.
     */
    public function mode(): string
    {
        $mode = $this->settings()['mode'] ?? config('notifier.settings.rate_limiting.mode', self::MODE_DROP);

        return $mode === self::MODE_DEFER ? self::MODE_DEFER : self::MODE_DROP;
    }

    /**
     * Get current count for a time period
     */
    public function getCurrentCount(string $period): int
    {
        $key = $this->getCacheKey($period);

        return (int) Cache::get($key, 0);
    }

    /**
     * Increment the global rate limit counters
     */
    public function increment(): void
    {
        $now = now();

        foreach (['minute', 'hour', 'day'] as $period) {
            $this->incrementCounter($this->getCacheKey($period, $now), $period);
        }
    }

    /**
     * Get rate limit status information
     */
    public function getStatus(): array
    {
        $limits = $this->globalLimits();

        return [
            'enabled' => $this->isEnabled(),
            'mode' => $this->mode(),
            'limits' => [
                'minute' => [
                    'max' => $limits['minute'],
                    'current' => $this->getCurrentCount('minute'),
                ],
                'hour' => [
                    'max' => $limits['hour'],
                    'current' => $this->getCurrentCount('hour'),
                ],
                'day' => [
                    'max' => $limits['day'],
                    'current' => $this->getCurrentCount('day'),
                ],
            ],
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->settings()['enabled'] ?? config('notifier.settings.rate_limiting.enabled', true));
    }

    /**
     * @return array<string, mixed>
     */
    protected function settings(): array
    {
        return NotificationSetting::getRateLimiting();
    }

    /**
     * @return array{minute: int, hour: int, day: int}
     */
    protected function globalLimits(): array
    {
        $settings = $this->settings();

        return [
            'minute' => (int) ($settings['max_per_minute'] ?? config('notifier.settings.rate_limiting.max_per_minute', 60)),
            'hour' => (int) ($settings['max_per_hour'] ?? config('notifier.settings.rate_limiting.max_per_hour', 1000)),
            'day' => (int) ($settings['max_per_day'] ?? config('notifier.settings.rate_limiting.max_per_day', 10000)),
        ];
    }

    /**
     * Every limit that applies to a notification for this user and channel.
     *
     * @return array<int, array{scope: string, period: string, max: int}>
     */
    protected function limitsFor(mixed $userId, ?string $channel): array
    {
        $settings = $this->settings();
        $limits = [];

        foreach ($this->globalLimits() as $period => $max) {
            $limits[] = ['scope' => 'global', 'period' => $period, 'max' => $max];
        }

        $perUser = [
            'hour' => (int) ($settings['max_per_user_per_hour'] ?? config('notifier.settings.rate_limiting.max_per_user_per_hour', 0)),
            'day' => (int) ($settings['max_per_user_per_day'] ?? config('notifier.settings.rate_limiting.max_per_user_per_day', 0)),
        ];

        if ($userId !== null) {
            foreach ($perUser as $period => $max) {
                if ($max > 0) {
                    $limits[] = ['scope' => "user:{$userId}", 'period' => $period, 'max' => $max];
                }
            }
        }

        $perChannel = (int) ($settings['max_per_channel_per_minute'] ?? config('notifier.settings.rate_limiting.max_per_channel_per_minute', 0));

        if ($channel !== null && $perChannel > 0) {
            $limits[] = ['scope' => "channel:{$channel}", 'period' => 'minute', 'max' => $perChannel];
        }

        return $limits;
    }

    /**
     * Check if limit is exceeded for a given period
     */
    protected function checkLimit(string $period, int $maxLimit): bool
    {
        $key = $this->getCacheKey($period);
        $count = (int) Cache::get($key, 0);

        if ($count >= $maxLimit) {
            Log::warning("Rate limit exceeded: {$period} limit ({$maxLimit})", [
                'current_count' => $count,
                'limit' => $maxLimit,
                'period' => $period,
            ]);

            return false;
        }

        return true;
    }

    protected function incrementCounter(string $key, string $period): int
    {
        Cache::add($key, 0, $this->ttlFor($period));

        return (int) Cache::increment($key);
    }

    protected function ttlFor(string $period): Carbon
    {
        return match ($period) {
            'minute' => now()->addMinutes(2),
            'hour' => now()->addHours(2),
            default => now()->addDays(2),
        };
    }

    protected function secondsUntilReset(string $period, Carbon $now): int
    {
        $end = match ($period) {
            'minute' => $now->copy()->endOfMinute(),
            'hour' => $now->copy()->endOfHour(),
            default => $now->copy()->endOfDay(),
        };

        return max(1, (int) ceil(abs($now->diffInSeconds($end))));
    }

    /**
     * Cache key for a counter scope. The global scope keeps the original key format.
     */
    protected function counterKey(string $scope, string $period, ?Carbon $time = null): string
    {
        if ($scope === 'global') {
            return $this->getCacheKey($period, $time);
        }

        $time ??= now();

        return "notifier:rate_limit:{$scope}:{$period}:".$this->periodStamp($period, $time);
    }

    /**
     * Get cache key for a time period
     */
    protected function getCacheKey(string $period, ?Carbon $time = null): string
    {
        $time ??= now();

        return "notifier:rate_limit:{$period}:".$this->periodStamp($period, $time);
    }

    protected function periodStamp(string $period, Carbon $time): string
    {
        return match ($period) {
            'minute' => $time->format('Y-m-d-H-i'),
            'hour' => $time->format('Y-m-d-H'),
            'day' => $time->format('Y-m-d'),
            default => (string) $time->timestamp,
        };
    }
}
