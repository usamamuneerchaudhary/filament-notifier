<?php

namespace Usamamuneerchaudhary\Notifier\Services\ChannelDrivers;

use Illuminate\Support\Facades\Http;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;

class SlackDriver implements ChannelDriverInterface
{
    public function send(Notification $notification): bool
    {
        try {
            $channel = NotificationChannel::where('type', 'slack')->first();

            if (! $channel || ! isset($channel->settings['webhook_url'])) {
                return false;
            }

            $response = Http::connectTimeout(3)->timeout(10)->post($channel->settings['webhook_url'], [
                'text' => $notification->subject,
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => $notification->content,
                        ],
                    ],
                ],
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Slack notification failed: '.$e->getMessage());

            return false;
        }
    }

    public function validateSettings(array $settings): bool
    {
        return ! empty($settings['webhook_url'] ?? null);
    }
}
