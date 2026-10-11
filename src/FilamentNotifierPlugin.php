<?php

namespace Usamamuneerchaudhary\Notifier;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Usamamuneerchaudhary\Notifier\Filament\Pages\EventChannelConfiguration;
use Usamamuneerchaudhary\Notifier\Filament\Pages\NotificationPreferences;
use Usamamuneerchaudhary\Notifier\Filament\Pages\NotificationSettings;
use Usamamuneerchaudhary\Notifier\Filament\Pages\NotifierDashboard;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationChannelResource;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationEventResource;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationResource;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationTemplateResource;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\NotificationAnalyticsChart;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\NotificationChannelPerformance;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\NotificationEngagementStats;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\NotificationStatsOverview;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\NotificationTimeSeriesChart;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\RateLimitingStatusWidget;

class FilamentNotifierPlugin implements Plugin
{
    protected bool|Closure $hasPreferencesPage = true;

    public function getId(): string
    {
        return 'notifier';
    }

    /**
     * Show or hide the self-service "Notification Preferences" page for panel users.
     */
    public function preferencesPage(bool|Closure $condition = true): static
    {
        $this->hasPreferencesPage = $condition;

        return $this;
    }

    public function hasPreferencesPage(): bool
    {
        return (bool) value($this->hasPreferencesPage);
    }

    public function register(Panel $panel): void
    {
        $pages = [
            NotifierDashboard::class,
            NotificationSettings::class,
            EventChannelConfiguration::class,
        ];

        if ($this->hasPreferencesPage()) {
            $pages[] = NotificationPreferences::class;
        }

        $panel
            ->widgets([
                NotificationStatsOverview::class,
                NotificationEngagementStats::class,
                NotificationTimeSeriesChart::class,
                NotificationAnalyticsChart::class,
                NotificationChannelPerformance::class,
                RateLimitingStatusWidget::class,
            ])
            ->resources([
                NotificationChannelResource::class,
                NotificationEventResource::class,
                NotificationTemplateResource::class,
                NotificationResource::class,
            ])
            ->pages($pages);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return new static;
    }
}
