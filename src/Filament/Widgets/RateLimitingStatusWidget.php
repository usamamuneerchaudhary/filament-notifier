<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;
use Usamamuneerchaudhary\Notifier\Services\RateLimitingService;

class RateLimitingStatusWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Rate Limiting Status';

    protected static ?int $sort = 6;

    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $rateLimiting = NotificationSetting::getRateLimiting();

        if (! ($rateLimiting['enabled'] ?? config('notifier.settings.rate_limiting.enabled', true))) {
            return [
                Stat::make('Rate Limiting', 'Disabled')
                    ->description('Rate limiting is currently disabled')
                    ->color('gray'),
            ];
        }

        $rateLimitingService = app(RateLimitingService::class);
        $status = $rateLimitingService->getStatus();

        $minuteUsage = $status['limits']['minute']['current'];
        $minuteMax = $status['limits']['minute']['max'];
        $minutePercent = $minuteMax > 0 ? round(($minuteUsage / $minuteMax) * 100, 1) : 0;

        $hourUsage = $status['limits']['hour']['current'];
        $hourMax = $status['limits']['hour']['max'];
        $hourPercent = $hourMax > 0 ? round(($hourUsage / $hourMax) * 100, 1) : 0;

        $dayUsage = $status['limits']['day']['current'];
        $dayMax = $status['limits']['day']['max'];
        $dayPercent = $dayMax > 0 ? round(($dayUsage / $dayMax) * 100, 1) : 0;

        return [
            Stat::make('Per Minute', $minuteUsage.' / '.$minuteMax)
                ->description($minutePercent.'% used')
                ->descriptionIcon($minutePercent > 80 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($this->getColorForPercent($minutePercent))
                ->chart([$minuteUsage, $minuteMax]),

            Stat::make('Per Hour', $hourUsage.' / '.$hourMax)
                ->description($hourPercent.'% used')
                ->descriptionIcon($hourPercent > 80 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($this->getColorForPercent($hourPercent)),

            Stat::make('Per Day', $dayUsage.' / '.$dayMax)
                ->description($dayPercent.'% used')
                ->descriptionIcon($dayPercent > 80 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($this->getColorForPercent($dayPercent)),
        ];
    }

    private function getColorForPercent(float $percent): string
    {
        if ($percent >= 90) {
            return 'danger';
        } elseif ($percent >= 75) {
            return 'warning';
        } else {
            return 'success';
        }
    }
}
