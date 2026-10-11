<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User ID Type
    |--------------------------------------------------------------------------
    |
    | The type of the user_id foreign key in notification tables. Set to 'uuid'
    | if your User model uses UUIDs as primary keys instead of auto-incrementing
    | integers. This must match your users table id column type, and must be set
    | before running the package migrations.
    |
    */
    'user_id_type' => env('NOTIFIER_USER_ID_TYPE', 'bigint'), // 'bigint' or 'uuid'

    /*
    |--------------------------------------------------------------------------
    | Default Notification Settings
    |--------------------------------------------------------------------------
    |
    | This option controls the default notification settings for the package.
    |
    */
    'defaults' => [
        'queue' => env('NOTIFIER_QUEUE', 'default'),
        'timeout' => env('NOTIFIER_TIMEOUT', 30),
        'retries' => env('NOTIFIER_RETRIES', 3),
        // Seconds to wait between delivery attempts (the last value is reused for any further attempts).
        'backoff' => [10, 60, 300],
    ],

    /*
    |--------------------------------------------------------------------------
    | Click Tracking Redirects
    |--------------------------------------------------------------------------
    |
    | Links the package rewrites are signed, so they can point anywhere. Older unsigned
    | links are only followed when the URL was part of that notification, or when the
    | host is listed here. Everything else redirects to the home page.
    |
    */
    'tracking' => [
        'allowed_hosts' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preferences API
    |--------------------------------------------------------------------------
    |
    | Set `middleware` to null to auto-detect: `api` + `auth:sanctum` when Laravel Sanctum
    | is installed, otherwise `web` + `auth`.
    |
    */
    'api' => [
        'enabled' => env('NOTIFIER_API_ENABLED', true),
        'prefix' => 'api/notifier/preferences',
        'middleware' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Unsubscribe
    |--------------------------------------------------------------------------
    |
    | Adds signed one-click unsubscribe links (and List-Unsubscribe headers) to emails.
    | Templates can also use the {{unsubscribe_url}} variable.
    |
    */
    'unsubscribe' => [
        'enabled' => env('NOTIFIER_UNSUBSCRIBE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery Webhooks
    |--------------------------------------------------------------------------
    |
    | Inbound provider callbacks are received at POST /notifier/webhooks/{provider}
    | (mailgun, postmark, ses, twilio) and update delivered, bounced and complained states.
    |
    */
    'webhooks' => [
        'enabled' => env('NOTIFIER_WEBHOOKS_ENABLED', true),
        'prefix' => 'notifier/webhooks',
        'middleware' => [],
        'mailgun' => [
            'signing_key' => env('NOTIFIER_MAILGUN_SIGNING_KEY'),
        ],
        'postmark' => [
            'username' => env('NOTIFIER_POSTMARK_WEBHOOK_USERNAME'),
            'password' => env('NOTIFIER_POSTMARK_WEBHOOK_PASSWORD'),
        ],
        'ses' => [
            // Optional allow list of SNS topic ARNs.
            'topic_arns' => [],
        ],
        'twilio' => [
            // Falls back to the SMS channel's twilio_auth_token setting.
            'auth_token' => env('NOTIFIER_TWILIO_AUTH_TOKEN'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | These settings are stored in the database (notifier_settings table) and
    | can be managed through the Filament admin panel. The values below are
    | used as fallbacks if no database values exist.
    |
    | Note: Channels, Events, and Templates are now managed entirely through
    | the database and Filament admin interface.
    |
    */
    'settings' => [
        'preferences' => [
            'enabled' => env('NOTIFIER_PREFERENCES_ENABLED', true),
            'default_channels' => ['email'],
            'allow_override' => env('NOTIFIER_ALLOW_OVERRIDE', true),
        ],
        'analytics' => [
            'enabled' => env('NOTIFIER_ANALYTICS_ENABLED', true),
            'track_opens' => env('NOTIFIER_TRACK_OPENS', true),
            'track_clicks' => env('NOTIFIER_TRACK_CLICKS', true),
            'retention_days' => env('NOTIFIER_RETENTION_DAYS', 90),
        ],
        'rate_limiting' => [
            'enabled' => env('NOTIFIER_RATE_LIMITING_ENABLED', true),
            'max_per_minute' => env('NOTIFIER_MAX_PER_MINUTE', 60),
            'max_per_hour' => env('NOTIFIER_MAX_PER_HOUR', 1000),
            'max_per_day' => env('NOTIFIER_MAX_PER_DAY', 10000),
            // What happens when a limit is hit: 'drop' skips the notification, 'defer' queues it for later.
            'mode' => env('NOTIFIER_RATE_LIMIT_MODE', 'drop'),
            // Optional extra limits (0 or null disables them).
            'max_per_user_per_hour' => env('NOTIFIER_MAX_PER_USER_PER_HOUR', 0),
            'max_per_user_per_day' => env('NOTIFIER_MAX_PER_USER_PER_DAY', 0),
            'max_per_channel_per_minute' => env('NOTIFIER_MAX_PER_CHANNEL_PER_MINUTE', 0),
        ],
        'template_cache' => env('NOTIFIER_TEMPLATE_CACHE', false),
        'log_unreplaced_variables' => env('NOTIFIER_LOG_UNREPLACED_VARIABLES', false),
    ],
];
