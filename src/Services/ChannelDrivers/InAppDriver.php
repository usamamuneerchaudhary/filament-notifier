<?php

namespace Usamamuneerchaudhary\Notifier\Services\ChannelDrivers;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Log;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;

/**
 * Delivers to Filament's own database notifications (the bell icon).
 *
 * Requires Laravel's `notifications` table and `->databaseNotifications()` on the panel.
 * Template data may include `action_url`, `action_label`, `status` (info|success|warning|danger) and `icon`.
 */
class InAppDriver implements ChannelDriverInterface
{
    public function send(Notification $notification): bool
    {
        try {
            $user = $notification->user;

            if (! $user || ! method_exists($user, 'notify')) {
                return false;
            }

            $settings = NotificationChannel::where('type', 'in_app')->first()?->settings ?? [];
            $data = $notification->data ?? [];

            $filamentNotification = FilamentNotification::make()
                ->title((string) $notification->subject)
                ->body((string) $notification->content);

            $status = $data['status'] ?? $settings['status'] ?? null;
            if (in_array($status, ['info', 'success', 'warning', 'danger'], true)) {
                $filamentNotification->status($status);
            }

            if (! empty($data['icon']) && is_string($data['icon'])) {
                $filamentNotification->icon($data['icon']);
            }

            if (! empty($data['action_url']) && is_string($data['action_url'])) {
                $filamentNotification->actions([
                    Action::make('open')
                        ->label((string) ($data['action_label'] ?? 'Open'))
                        ->url($data['action_url']),
                ]);
            }

            $filamentNotification->sendToDatabase($user, isEventDispatched: true);

            if (filter_var($settings['broadcast'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $filamentNotification->broadcast($user);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('In-app notification failed: '.$e->getMessage());

            return false;
        }
    }

    public function validateSettings(array $settings): bool
    {
        return true;
    }
}
