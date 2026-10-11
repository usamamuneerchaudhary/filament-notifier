<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;

class NotificationAnalyticsChart extends ChartWidget
{
    protected ?string $heading = 'Engagement Analytics (Last 7 Days)';

    protected static ?int $sort = 4;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return false;
    }

    protected function getData(): array
    {
        $analytics = NotificationSetting::getAnalytics();

        if (! ($analytics['enabled'] ?? config('notifier.settings.analytics.enabled', true))) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $labels = [];
        $opensData = [];
        $clicksData = [];
        $openRateData = [];
        $clickRateData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');

            $sent = Notification::whereDate('created_at', $date)->dispatched()->count();
            $opens = Notification::whereDate('opened_at', $date)->sum('opens_count');
            $clicks = Notification::whereDate('clicked_at', $date)->sum('clicks_count');

            $opensData[] = $opens;
            $clicksData[] = $clicks;
            $openRateData[] = $sent > 0 ? round(($opens / $sent) * 100, 1) : 0;
            $clickRateData[] = $sent > 0 ? round(($clicks / $sent) * 100, 1) : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Opens',
                    'data' => $opensData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Clicks',
                    'data' => $clicksData,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.5)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Open Rate %',
                    'data' => $openRateData,
                    'type' => 'line',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'yAxisID' => 'y1',
                    'fill' => false,
                ],
                [
                    'label' => 'Click Rate %',
                    'data' => $clickRateData,
                    'type' => 'line',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'yAxisID' => 'y1',
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'beginAtZero' => true,
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'beginAtZero' => true,
                    'max' => 100,
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
        ];
    }
}
