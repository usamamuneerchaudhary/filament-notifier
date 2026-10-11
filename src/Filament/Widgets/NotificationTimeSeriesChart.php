<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Usamamuneerchaudhary\Notifier\Models\Notification;

class NotificationTimeSeriesChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Notifications Over Time';

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        $days = 30;
        $labels = [];
        $sentData = [];
        $openedData = [];
        $clickedData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');

            $sentData[] = Notification::whereDate('created_at', $date)->count();
            $openedData[] = Notification::whereDate('opened_at', $date)->count();
            $clickedData[] = Notification::whereDate('clicked_at', $date)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sent',
                    'data' => $sentData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => false,
                ],
                [
                    'label' => 'Opened',
                    'data' => $openedData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'fill' => false,
                ],
                [
                    'label' => 'Clicked',
                    'data' => $clickedData,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.5)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
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
