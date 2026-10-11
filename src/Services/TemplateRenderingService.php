<?php

namespace Usamamuneerchaudhary\Notifier\Services;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;
use Usamamuneerchaudhary\Notifier\Models\NotificationTemplate;

class TemplateRenderingService
{
    /**
     * Render a notification template with data
     */
    public function render(NotificationTemplate $template, array $data, string $channelType = 'email'): array
    {
        $subject = $template->subject ?? '';
        $content = $template->content ?? '';

        $allData = $this->prepareData($data);

        // Values are HTML-escaped only for HTML emails, so plain text channels keep apostrophes and ampersands intact.
        $escapeValues = $channelType === 'email' && $this->isHtml($content);

        $subject = $this->replaceVariables($subject, $allData, false);
        $content = $this->replaceVariables($content, $allData, $escapeValues);

        $this->logUnreplacedVariables($subject, $content, $allData, $template);

        // Apply analytics tracking if enabled and for email channel
        if ($channelType === 'email' && isset($data['tracking_token'])) {
            $analyticsService = app(AnalyticsService::class);
            if ($analyticsService->isClickTrackingEnabled()) {
                $urlTrackingService = app(UrlTrackingService::class);
                $content = $urlTrackingService->rewriteUrlsForTracking($content, $data['tracking_token']);
            }
        }

        return [
            'subject' => $subject,
            'content' => $content,
        ];
    }

    /**
     * Prepare data array with default values and user information
     */
    protected function prepareData(array $data): array
    {
        $allData = array_merge([
            'app_name' => config('app.name', 'Laravel'),
            'app_url' => config('app.url', ''),
        ], $data);

        if (isset($data['user']) && is_object($data['user'])) {
            $user = $data['user'];
            $allData['user_name'] = $user->name ?? '';
            $allData['user_email'] = $user->email ?? '';
            $allData['name'] = $allData['name'] ?? ($user->name ?? '');
            $allData['email'] = $allData['email'] ?? ($user->email ?? '');
        }

        return $allData;
    }

    /**
     * Replace template variables in content.
     *
     * `{{ name }}` is escaped when $escape is true, `{{{ name }}}` always inserts the raw value.
     */
    protected function replaceVariables(string $content, array $data, bool $escape = false): string
    {
        $pattern = '/\{\{\{\s*([a-zA-Z0-9_]+)\s*\}\}\}|\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/';

        return preg_replace_callback($pattern, function ($matches) use ($data, $escape) {
            $isRaw = ($matches[1] ?? '') !== '';
            $varName = $isRaw ? $matches[1] : $matches[2];

            if (! isset($data[$varName])) {
                return $matches[0];
            }

            $value = $data[$varName];

            if ($value instanceof Htmlable) {
                return $value->toHtml();
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            if (! is_scalar($value) && ! $value instanceof \Stringable) {
                return $matches[0];
            }

            $value = (string) $value;

            return $escape && ! $isRaw ? e($value) : $value;
        }, $content);
    }

    protected function isHtml(string $content): bool
    {
        return strip_tags($content) !== $content;
    }

    /**
     * Log unreplaced template variables if enabled
     */
    protected function logUnreplacedVariables(string $subject, string $content, array $allData, NotificationTemplate $template): void
    {
        $logUnreplaced = NotificationSetting::get('log_unreplaced_variables', config('notifier.settings.log_unreplaced_variables', false));
        if (! $logUnreplaced) {
            return;
        }

        $pattern = '/\{\{\{?\s*([a-zA-Z0-9_]+)\s*\}?\}\}/';
        preg_match_all($pattern, $subject.$content, $unreplaced);

        if (! empty($unreplaced[1])) {
            $missing = array_unique($unreplaced[1]);
            $missing = array_filter($missing, fn ($var) => ! isset($allData[$var]));
            if (! empty($missing)) {
                Log::warning('Unreplaced template variables: '.implode(', ', $missing), [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                ]);
            }
        }
    }
}
