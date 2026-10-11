<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;

class NotificationStatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '10s';

    public static function canView(): bool
    {
        return false;
    }

    protected function getStats(): array
    {
        $totalNotifications = Notification::count();
        $sentNotifications = Notification::dispatched()->count();
        $pendingNotifications = Notification::whereIn('status', ['pending', 'sending'])->count();
        $failedNotifications = Notification::where('status', 'failed')->count();
        $activeChannels = NotificationChannel::where('is_active', true)->count();

        // Calculate success rate
        $successRate = $totalNotifications > 0 ? round(($sentNotifications / $totalNotifications) * 100, 1) : 0;

        // Get recent notifications for chart data
        $recentNotifications = $this->getRecentNotificationsData();

        return [
            Stat::make('Total Notifications', $totalNotifications)
                ->description('All time notifications')
                ->descriptionIcon('heroicon-m-envelope')
                ->chart($recentNotifications)
                ->color('success'),

            Stat::make('Success Rate', $successRate.'%')
                ->description($sentNotifications.' of '.$totalNotifications.' sent successfully')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Pending Notifications', $pendingNotifications)
                ->description('Awaiting delivery')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Failed Notifications', $failedNotifications)
                ->description('Failed to deliver')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Active Channels', $activeChannels)
                ->description('Enabled notification channels')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('info'),
        ];
    }

    private function getRecentNotificationsData(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Notification::whereDate('created_at', $date)->count();
            $data[] = $count;
        }

        return $data;
    }
}
