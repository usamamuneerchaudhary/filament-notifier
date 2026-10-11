<?php

namespace Usamamuneerchaudhary\Notifier\Services\Webhooks;

use Illuminate\Http\Request;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;

class TwilioWebhookHandler implements WebhookHandler
{
    public function verify(Request $request): bool
    {
        $authToken = $this->authToken();
        $provided = (string) $request->header('X-Twilio-Signature', '');

        if ($authToken === '' || $provided === '') {
            return false;
        }

        $params = $request->post();
        ksort($params);

        $payload = $request->fullUrl();
        foreach ($params as $key => $value) {
            $payload .= $key.(is_array($value) ? implode('', $value) : $value);
        }

        $expected = base64_encode(hash_hmac('sha1', $payload, $authToken, true));

        return hash_equals($expected, $provided);
    }

    public function acknowledge(Request $request): bool
    {
        return false;
    }

    public function parse(Request $request): array
    {
        $sid = (string) $request->input('MessageSid', $request->input('SmsSid', ''));
        if ($sid === '') {
            return [];
        }

        $status = match ($request->input('MessageStatus', $request->input('SmsStatus'))) {
            'delivered' => NotificationStatus::Delivered,
            'undelivered', 'failed' => NotificationStatus::Bounced,
            default => null,
        };

        if ($status === null) {
            return [];
        }

        $errorCode = $request->input('ErrorCode');

        return [new DeliveryReport($sid, $status, $errorCode ? "Twilio error {$errorCode}" : null)];
    }

    protected function authToken(): string
    {
        $configured = (string) config('notifier.webhooks.twilio.auth_token', '');

        if ($configured !== '') {
            return $configured;
        }

        return (string) (NotificationChannel::where('type', 'sms')->first()?->settings['twilio_auth_token'] ?? '');
    }
}
