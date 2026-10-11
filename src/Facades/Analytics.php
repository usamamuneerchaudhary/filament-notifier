<?php

namespace Usamamuneerchaudhary\Notifier\Facades;

use Illuminate\Support\Facades\Facade;
use Usamamuneerchaudhary\Notifier\Services\AnalyticsService;

/**
 * @method static bool isEnabled()
 * @method static bool isOpenTrackingEnabled()
 * @method static bool isClickTrackingEnabled()
 * @method static string generateTrackingPixel(string $trackingToken)
 * @method static void trackOpen(\Usamamuneerchaudhary\Notifier\Models\Notification $notification)
 * @method static void trackClick(\Usamamuneerchaudhary\Notifier\Models\Notification $notification)
 *
 * @see AnalyticsService
 */
class Analytics extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return AnalyticsService::class;
    }
}
