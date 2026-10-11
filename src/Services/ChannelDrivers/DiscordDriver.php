<?php

namespace Usamamuneerchaudhary\Notifier\Services\ChannelDrivers;

use Illuminate\Support\Facades\Http;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;

class DiscordDriver implements ChannelDriverInterface
{
    public function send(Notification $notification): bool
    {
        try {
            $channel = NotificationChannel::where('type', 'discord')->first();

            if (! $channel || ! isset($channel->settings['webhook_url'])) {
                return false;
            }

            $settings = $channel->settings;
            $webhookUrl = $settings['webhook_url'];

            // Discord webhook payload
            $payload = [
                'content' => $notification->subject,
                'embeds' => [
                    [
                        'title' => $notification->subject,
                        'description' => $notification->content,
                        'color' => $settings['color'] ?? 3447003, // Default blue color
                        'timestamp' => now()->toIso8601String(),
                    ],
                ],
            ];

            if (isset($settings['username'])) {
                $payload['username'] = $settings['username'];
            }

            if (isset($settings['avatar_url'])) {
                $payload['avatar_url'] = $settings['avatar_url'];
            }

            $response = Http::connectTimeout(3)->timeout(10)->post($webhookUrl, $payload);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Discord notification failed: '.$e->getMessage());

            return false;
        }
    }

    public function validateSettings(array $settings): bool
    {
        return ! empty($settings['webhook_url'] ?? null);
    }
}
