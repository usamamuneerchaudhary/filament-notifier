<?php

namespace Usamamuneerchaudhary\Notifier\Services\ChannelDrivers;

use Illuminate\Support\Facades\Http;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;

class SmsDriver implements ChannelDriverInterface, ProvidesDeliveryReference
{
    protected ?string $messageId = null;

    public function send(Notification $notification): bool
    {
        try {
            $channel = NotificationChannel::where('type', 'sms')->first();

            if (! $channel) {
                return false;
            }

            $user = $notification->user;
            if (! $user || ! $user->phone) {
                return false;
            }

            $settings = $channel->settings;

            if (isset($settings['twilio_account_sid']) && isset($settings['twilio_auth_token'])) {
                return $this->sendViaTwilio($notification, $settings, $user->phone);
            }

            return $this->sendViaGenericApi($notification, $settings, $user->phone);
        } catch (\Exception $e) {
            \Log::error('SMS notification failed: '.$e->getMessage());

            return false;
        }
    }

    protected function sendViaTwilio(Notification $notification, array $settings, string $phone): bool
    {
        $payload = [
            'To' => $phone,
            'From' => $settings['twilio_phone_number'],
            'Body' => $notification->content,
        ];

        if (config('notifier.webhooks.enabled', true)) {
            $payload['StatusCallback'] = url(config('notifier.webhooks.prefix', 'notifier/webhooks').'/twilio');
        }

        $response = Http::asForm()
            ->connectTimeout(3)
            ->timeout(10)
            ->withBasicAuth($settings['twilio_account_sid'], $settings['twilio_auth_token'])
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$settings['twilio_account_sid']}/Messages.json", $payload);

        $sid = $response->json('sid');
        $this->messageId = is_string($sid) ? $sid : null;

        return $response->successful();
    }

    protected function sendViaGenericApi(Notification $notification, array $settings, string $phone): bool
    {
        if (! isset($settings['api_url']) || ! isset($settings['api_key'])) {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$settings['api_key'],
            'Content-Type' => 'application/json',
        ])->connectTimeout(3)->timeout(10)->post($settings['api_url'], [
            'to' => $phone,
            'message' => $notification->content,
        ]);

        return $response->successful();
    }

    public function deliveryReference(): ?string
    {
        return $this->messageId;
    }

    public function validateSettings(array $settings): bool
    {
        // Check for either Twilio settings or generic API settings
        $hasTwilio = ! empty($settings['twilio_account_sid'] ?? null) &&
                    ! empty($settings['twilio_auth_token'] ?? null) &&
                    ! empty($settings['twilio_phone_number'] ?? null);

        $hasGenericApi = ! empty($settings['api_url'] ?? null) &&
                        ! empty($settings['api_key'] ?? null);

        return $hasTwilio || $hasGenericApi;
    }
}
