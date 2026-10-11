<?php

namespace Usamamuneerchaudhary\Notifier\Services;

use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;

class AnalyticsService
{
    /**
     * Check if analytics is enabled
     */
    public function isEnabled(): bool
    {
        $analytics = NotificationSetting::getAnalytics();

        return $analytics['enabled'] ?? config('notifier.settings.analytics.enabled', true);
    }

    /**
     * Check if open tracking is enabled
     */
    public function isOpenTrackingEnabled(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $analytics = NotificationSetting::getAnalytics();

        return $analytics['track_opens'] ?? config('notifier.settings.analytics.track_opens', true);
    }

    /**
     * Check if click tracking is enabled
     */
    public function isClickTrackingEnabled(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $analytics = NotificationSetting::getAnalytics();

        return $analytics['track_clicks'] ?? config('notifier.settings.analytics.track_clicks', true);
    }

    /**
     * Generate tracking pixel HTML for email open tracking
     */
    public function generateTrackingPixel(string $trackingToken): string
    {
        $appUrl = rtrim(config('app.url', ''), '/');
        $trackingPixelUrl = "{$appUrl}/notifier/track/open/{$trackingToken}";

        return '<img src="'.htmlspecialchars($trackingPixelUrl, ENT_QUOTES, 'UTF-8').'" width="1" height="1" style="display:none;" alt="" />';
    }

    /**
     * Track notification open
     */
    public function trackOpen(Notification $notification): void
    {
        if (! $this->isOpenTrackingEnabled()) {
            return;
        }

        $notification->increment('opens_count');

        if (! $notification->opened_at) {
            $notification->update(['opened_at' => now()]);
        }
    }

    /**
     * Track notification click
     */
    public function trackClick(Notification $notification): void
    {
        if (! $this->isClickTrackingEnabled()) {
            return;
        }

        $notification->increment('clicks_count');

        if (! $notification->clicked_at) {
            $notification->update(['clicked_at' => now()]);
        }
    }
}
