<?php

namespace Usamamuneerchaudhary\Notifier\Facades;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Facade;
use Usamamuneerchaudhary\Notifier\Services\UrlTrackingService;

/**
 * @method static RedirectResponse safeRedirect(string $url)
 * @method static string rewriteUrlsForTracking(string $content, string $token)
 *
 * @see UrlTrackingService
 */
class UrlTracking extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return UrlTrackingService::class;
    }
}
