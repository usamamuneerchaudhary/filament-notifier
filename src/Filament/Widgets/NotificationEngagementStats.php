<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;

class NotificationEngagementStats extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return false;
    }

    protected function getStats(): array
    {
        $analytics = NotificationSetting::getAnalytics();

        if (! ($analytics['enabled'] ?? config('notifier.settings.analytics.enabled', true))) {
            return [
                Stat::make('Analytics Disabled', '')
                    ->description('Enable analytics in settings to view engagement metrics')
                    ->color('gray'),
            ];
        }

        $totalSent = Notification::dispatched()->count();
        $totalOpened = Notification::whereNotNull('opened_at')->count();
        $totalClicked = Notification::whereNotNull('clicked_at')->count();

        $totalOpens = Notification::sum('opens_count');
        $totalClicks = Notification::sum('clicks_count');

        // Calculate engagement rates
        $openRate = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 1) : 0;
        $clickRate = $totalSent > 0 ? round(($totalClicked / $totalSent) * 100, 1) : 0;
        $clickThroughRate = $totalOpened > 0 ? round(($totalClicked / $totalOpened) * 100, 1) : 0;

        return [
            Stat::make('Total Opens', number_format($totalOpens))
                ->description($totalOpened.' unique opens')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info')
                ->chart($this->getOpensChartData()),

            Stat::make('Open Rate', $openRate.'%')
                ->description($totalOpened.' of '.$totalSent.' opened (inflated by Apple Mail Privacy Protection; prefer click rate)')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Total Clicks', number_format($totalClicks))
                ->description($totalClicked.' unique clicks')
                ->descriptionIcon('heroicon-m-cursor-arrow-rays')
                ->color('warning')
                ->chart($this->getClicksChartData()),

            Stat::make('Click Rate', $clickRate.'%')
                ->description($totalClicked.' of '.$totalSent.' emails clicked')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),

            Stat::make('Click-Through Rate', $clickThroughRate.'%')
                ->description('Clicks per open')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('success'),
        ];
    }

    private function getOpensChartData(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Notification::whereDate('opened_at', $date)
                ->sum('opens_count');
            $data[] = $count;
        }

        return $data;
    }

    private function getClicksChartData(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Notification::whereDate('clicked_at', $date)
                ->sum('clicks_count');
            $data[] = $count;
        }

        return $data;
    }
}
