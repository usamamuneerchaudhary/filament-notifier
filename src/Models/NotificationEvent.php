<?php

namespace Usamamuneerchaudhary\Notifier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class NotificationEvent extends Model
{
    protected $table = 'notifier_events';

    protected $fillable = [
        'group',
        'name',
        'key',
        'description',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Check if settings column exists before trying to save
            try {
                $columns = Schema::getColumnListing($model->getTable());
                if (! in_array('settings', $columns)) {
                    // Remove settings from attributes if no column
                    unset($model->attributes['settings']);
                    unset($model->original['settings']);
                } elseif (empty($model->settings) || $model->settings === []) {

                    $model->settings = null;
                }
            } catch (\Exception $e) {
                // If we cant check, just unset it to be safe
                unset($model->attributes['settings']);
            }
        });
    }

    /**
     * Channel types configured for this event. Accepts an array or a comma separated string
     * (the settings key/value editor stores strings).
     *
     * @return array<int, string>
     */
    public function channelList(): array
    {
        $channels = $this->settings['channels'] ?? null;

        if (is_string($channels)) {
            $channels = explode(',', $channels);
        }

        if (! is_array($channels)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($channel): string => trim((string) $channel),
            $channels
        )));
    }

    /**
     * Channel to fall back to when delivery on $channel permanently fails.
     *
     * Configure with `fallbacks => ['email' => 'sms']` or the key/value setting `fallback.email = sms`.
     */
    public function fallbackChannelFor(string $channel): ?string
    {
        $settings = $this->settings ?? [];
        $fallback = $settings['fallbacks'][$channel] ?? $settings["fallback.{$channel}"] ?? null;

        if (! is_string($fallback)) {
            return null;
        }

        $fallback = trim($fallback);

        return $fallback !== '' && $fallback !== $channel ? $fallback : null;
    }

    public function templates(): HasMany
    {
        return $this->hasMany(NotificationTemplate::class, 'event_key', 'key');
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }
}
