<?php

namespace Usamamuneerchaudhary\Notifier\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $table = 'notifier_settings';

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
            ]
        );
    }

    /**
     * Get all preference settings
     */
    public static function getPreferences(): array
    {
        $preferences = static::get('preferences', config('notifier.settings.preferences', []));

        return is_array($preferences) ? $preferences : [];
    }

    /**
     * Get all analytics settings
     */
    public static function getAnalytics(): array
    {
        $analytics = static::get('analytics', config('notifier.settings.analytics', []));

        return is_array($analytics) ? $analytics : [];
    }

    /**
     * Get all rate limiting settings
     */
    public static function getRateLimiting(): array
    {
        $rateLimiting = static::get('rate_limiting', config('notifier.settings.rate_limiting', []));

        return is_array($rateLimiting) ? $rateLimiting : [];
    }
}
