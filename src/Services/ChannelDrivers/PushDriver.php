<?php

namespace Usamamuneerchaudhary\Notifier\Services\ChannelDrivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Services\FcmAccessTokenProvider;

/**
 * Push notifications through Firebase Cloud Messaging (HTTP v1 API).
 *
 * Settings: `firebase_project_id` and `firebase_service_account` (the service account JSON, or a path to it).
 * The device token is read from `$user->fcm_token` or `$user->push_token`.
 */
class PushDriver implements ChannelDriverInterface, ProvidesDeliveryReference
{
    protected ?string $messageId = null;

    public function send(Notification $notification): bool
    {
        try {
            $channel = NotificationChannel::where('type', 'push')->first();

            if (! $channel || ! $this->validateSettings($channel->settings ?? [])) {
                Log::warning('Push notification failed: configure firebase_project_id and firebase_service_account (the legacy FCM server key is no longer supported).');

                return false;
            }

            $user = $notification->user;
            if (! $user) {
                return false;
            }

            $deviceToken = $user->fcm_token ?? $user->push_token ?? null;

            if (! $deviceToken) {
                Log::warning("Push notification failed: No FCM token for user {$user->id}");

                return false;
            }

            $settings = $channel->settings;
            $serviceAccount = $this->serviceAccount($settings['firebase_service_account']);
            $accessToken = app(FcmAccessTokenProvider::class)->token($serviceAccount);

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$settings['firebase_project_id']}/messages:send", [
                    'message' => array_filter([
                        'token' => $deviceToken,
                        'notification' => [
                            'title' => (string) $notification->subject,
                            'body' => (string) $notification->content,
                        ],
                        'data' => $this->dataPayload($notification),
                    ]),
                ]);

            if (! $response->successful()) {
                Log::error('Push notification failed: '.$response->body());

                return false;
            }

            $name = $response->json('name');
            $this->messageId = is_string($name) ? $name : null;

            return true;
        } catch (\Throwable $e) {
            Log::error('Push notification failed: '.$e->getMessage());

            return false;
        }
    }

    public function deliveryReference(): ?string
    {
        return $this->messageId;
    }

    public function validateSettings(array $settings): bool
    {
        return ! empty($settings['firebase_project_id'] ?? null)
            && ! empty($settings['firebase_service_account'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serviceAccount(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $value = trim((string) $value);

        if (! str_starts_with($value, '{') && is_file($value)) {
            $value = (string) file_get_contents($value);
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * FCM data payload values must all be strings.
     *
     * @return array<string, string>
     */
    protected function dataPayload(Notification $notification): array
    {
        $payload = [];

        foreach (($notification->data ?? []) as $key => $value) {
            if ($key === 'tracking_token' || (! is_scalar($value) && ! $value instanceof \Stringable)) {
                continue;
            }

            $payload[(string) $key] = (string) $value;
        }

        return $payload;
    }
}
