<?php

namespace Usamamuneerchaudhary\Notifier\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Usamamuneerchaudhary\Notifier\Services\AnalyticsService;
use Usamamuneerchaudhary\Notifier\Services\NotificationRepository;
use Usamamuneerchaudhary\Notifier\Services\UrlTrackingService;

class NotificationTrackingController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService,
        protected NotificationRepository $notificationRepository,
        protected UrlTrackingService $urlTrackingService
    ) {}

    /**
     * Track email open - returns 1x1 transparent pixel
     */
    public function trackOpen(string $token): Response
    {
        try {
            $notification = $this->notificationRepository->findByToken($token);

            if (! $notification) {
                return $this->transparentPixel();
            }

            if (! $this->analyticsService->isOpenTrackingEnabled()) {
                return $this->transparentPixel();
            }

            $this->analyticsService->trackOpen($notification);
        } catch (\Exception $e) {
            Log::error('Failed to track notification open: '.$e->getMessage(), [
                'token' => $token,
            ]);
        }

        return $this->transparentPixel();
    }

    /**
     * Track link click - redirects to original URL.
     *
     * Signed links (and URLs that were part of the notification) may point anywhere over http(s).
     * Anything else only redirects within this site or to the configured allowed hosts.
     */
    public function trackClick(string $token, Request $request): RedirectResponse
    {
        $originalUrl = (string) $request->query('url', '/');

        try {
            if ($originalUrl === '' || $originalUrl === '/') {
                return redirect()->to('/');
            }

            $notification = $this->notificationRepository->findByToken($token);

            $isTrusted = $request->hasValidRelativeSignature()
                || ($notification !== null && $this->urlTrackingService->wasLinkedInNotification($notification, $originalUrl));

            if ($notification && $this->analyticsService->isClickTrackingEnabled()) {
                $this->analyticsService->trackClick($notification);
            }

            return $isTrusted
                ? $this->urlTrackingService->safeRedirect($originalUrl)
                : $this->urlTrackingService->restrictedRedirect($originalUrl, $request);
        } catch (\Exception $e) {
            Log::error('Failed to track notification click: '.$e->getMessage(), [
                'token' => $token,
                'url' => $originalUrl,
            ]);

            return $this->urlTrackingService->restrictedRedirect($originalUrl, $request);
        }
    }

    /**
     * Return 1x1 transparent PNG pixel
     */
    protected function transparentPixel(): Response
    {
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        return response($pixel, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
