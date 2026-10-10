<?php

namespace Usamamuneerchaudhary\Notifier\Services;

use Illuminate\Support\Facades\Cache;
use Usamamuneerchaudhary\Notifier\Models\Notification;

class NotificationRepository
{
    /**
     * Get notification by tracking token (cache first, then the indexed column, then legacy JSON data).
     */
    public function findByToken(string $token): ?Notification
    {
        $notificationId = Cache::get("notifier:tracking_token:{$token}");

        if ($notificationId) {
            $notification = Notification::find($notificationId);

            if ($notification) {
                return $notification;
            }
        }

        $notification = Notification::where('tracking_token', $token)->first()
            ?? Notification::whereJsonContains('data->tracking_token', $token)->first();

        if ($notification) {
            Cache::put("notifier:tracking_token:{$token}", $notification->id, now()->addDays(30));
        }

        return $notification;
    }

    /**
     * Get notification by the provider's message id (used by delivery webhooks).
     */
    public function findByProviderMessageId(string $messageId): ?Notification
    {
        return Notification::where('provider_message_id', $messageId)->latest('id')->first();
    }
}
