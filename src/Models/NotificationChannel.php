<?php

namespace Usamamuneerchaudhary\Notifier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationChannel extends Model
{
    protected $table = 'notifier_channels';

    protected $fillable = [
        'title',
        'type',
        'icon',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'channel', 'type');
    }
}
