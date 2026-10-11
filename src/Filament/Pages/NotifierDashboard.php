<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\NotificationAnalyticsChart;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\NotificationChannelPerformance;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\NotificationEngagementStats;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\NotificationStatsOverview;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\NotificationTimeSeriesChart;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\RateLimitingStatusWidget;

class NotifierDashboard extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|null|\UnitEnum $navigationGroup = 'Notifier';

    protected static ?string $title = 'Notifier Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            NotificationStatsOverview::class,
            NotificationEngagementStats::class,
            NotificationTimeSeriesChart::class,
            NotificationAnalyticsChart::class,
            NotificationChannelPerformance::class,
            RateLimitingStatusWidget::class,
        ];
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 2;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getWidgetsContentComponent(),
            ]);
    }

    public function getWidgetsContentComponent(): Component
    {
        return Grid::make($this->getColumns())
            ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getWidgets()));
    }
}
