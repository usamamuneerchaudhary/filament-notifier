<?php

namespace Usamamuneerchaudhary\Notifier\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Usamamuneerchaudhary\Notifier\Models\Notification;

class UrlTrackingService
{
    /**
     * Redirect to a URL that is already trusted (signed link or a URL from the notification itself).
     *
     * Only http(s) targets and relative paths are followed.
     */
    public function safeRedirect(string $url): RedirectResponse
    {
        $dangerousProtocols = ['javascript:', 'data:', 'vbscript:', 'file:', 'about:'];
        $urlLower = strtolower(trim($url));

        foreach ($dangerousProtocols as $protocol) {
            if (str_starts_with($urlLower, $protocol)) {
                return redirect()->to('/');
            }
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
                return redirect()->to('/');
            }

            if (! str_starts_with($url, '/')) {
                $url = '/'.$url;
            }

            return redirect()->to($url);
        }

        // Only allow http/https protocols
        $parsedUrl = parse_url($url);
        if (isset($parsedUrl['scheme']) && ! in_array(strtolower($parsedUrl['scheme']), ['http', 'https'], true)) {
            return redirect()->to('/');
        }

        return redirect()->away($url);
    }

    /**
     * Redirect for links we cannot vouch for (unsigned and not part of the notification).
     *
     * Only relative paths, this site's own host and the configured allowed hosts are followed.
     */
    public function restrictedRedirect(string $url, ?Request $request = null): RedirectResponse
    {
        return $this->isSameSiteTarget($url, $request) ? $this->safeRedirect($url) : redirect()->to('/');
    }

    public function isSameSiteTarget(string $url, ?Request $request = null): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $parsedUrl = parse_url($url);

        if ($parsedUrl === false) {
            return false;
        }

        if (! isset($parsedUrl['scheme']) && ! isset($parsedUrl['host'])) {
            return true;
        }

        $host = strtolower((string) ($parsedUrl['host'] ?? ''));

        return $host !== '' && in_array($host, $this->trustedHosts($request), true);
    }

    /**
     * Whether the URL is one of the links that was placed in this notification's content.
     */
    public function wasLinkedInNotification(Notification $notification, string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $needle = htmlspecialchars('url='.urlencode($url), ENT_QUOTES, 'UTF-8');

        return str_contains((string) $notification->content, $needle);
    }

    /**
     * Build a signed click tracking URL for a link.
     */
    public function trackingUrl(string $token, string $originalUrl): string
    {
        $path = URL::signedRoute('notifier.track.click', [
            'token' => $token,
            'url' => $originalUrl,
        ], null, false);

        return rtrim((string) config('app.url', ''), '/').$path;
    }

    /**
     * Rewrite URLs in content for click tracking
     */
    public function rewriteUrlsForTracking(string $content, string $token): string
    {
        $pattern = '/href=["\']([^"\']+)["\']/i';

        return preg_replace_callback($pattern, function ($matches) use ($token) {
            $originalUrl = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');

            if ($this->shouldSkipTracking($originalUrl)) {
                return $matches[0];
            }

            return 'href="'.htmlspecialchars($this->trackingUrl($token, $originalUrl), ENT_QUOTES, 'UTF-8').'"';
        }, $content);
    }

    protected function shouldSkipTracking(string $url): bool
    {
        $lower = strtolower(trim($url));

        return str_contains($url, '/notifier/track/')
            || str_contains($url, '/notifier/unsubscribe')
            || str_starts_with($lower, 'mailto:')
            || str_starts_with($lower, 'tel:')
            || str_starts_with($lower, 'sms:')
            || str_starts_with($lower, '#');
    }

    /**
     * @return array<int, string>
     */
    protected function trustedHosts(?Request $request): array
    {
        $hosts = array_map('strtolower', (array) config('notifier.tracking.allowed_hosts', []));

        $appHost = parse_url((string) config('app.url', ''), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = strtolower($appHost);
        }

        if ($request !== null) {
            $hosts[] = strtolower($request->getHost());
        }

        return array_values(array_unique($hosts));
    }
}
