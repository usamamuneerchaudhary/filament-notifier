<?php

namespace Usamamuneerchaudhary\Notifier\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;

class Notification extends Model
{
    protected $table = 'notifier_notifications';

    protected $fillable = [
        'notification_template_id',
        'user_id',
        'channel',
        'event_key',
        'subject',
        'content',
        'data',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'bounced_at',
        'complained_at',
        'status',
        'attempts',
        'next_retry_at',
        'error',
        'tracking_token',
        'provider_message_id',
        'fallback_for_notification_id',
        'opened_at',
        'clicked_at',
        'opens_count',
        'clicks_count',
    ];

    protected $casts = [
        'data' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'bounced_at' => 'datetime',
        'complained_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function fallbackFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fallback_for_notification_id');
    }

    /**
     * Notifications the provider accepted at some point (sent, delivered, bounced or complained).
     */
    public function scopeDispatched(Builder $query): Builder
    {
        return $query->whereIn('status', NotificationStatus::dispatchedValues());
    }

    public function statusEnum(): ?NotificationStatus
    {
        return NotificationStatus::tryFrom((string) $this->status);
    }

    public function canBeRetried(): bool
    {
        $status = $this->statusEnum();

        return $status !== null && in_array($status, NotificationStatus::retryable(), true);
    }

    public function isFallback(): bool
    {
        return $this->fallback_for_notification_id !== null;
    }
}
