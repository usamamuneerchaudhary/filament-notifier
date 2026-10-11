<?php

namespace Usamamuneerchaudhary\Notifier\Services\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;

class MailgunWebhookHandler implements WebhookHandler
{
    public function verify(Request $request): bool
    {
        $key = (string) config('notifier.webhooks.mailgun.signing_key', '');
        $signature = $request->input('signature');

        if ($key === '' || ! is_array($signature)) {
            return false;
        }

        $timestamp = (string) ($signature['timestamp'] ?? '');
        $token = (string) ($signature['token'] ?? '');
        $provided = (string) ($signature['signature'] ?? '');

        if ($timestamp === '' || $token === '' || $provided === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.$token, $key), $provided);
    }

    public function acknowledge(Request $request): bool
    {
        return false;
    }

    public function parse(Request $request): array
    {
        $event = $request->input('event-data');

        if (! is_array($event)) {
            return [];
        }

        $messageId = trim((string) data_get($event, 'message.headers.message-id', ''), '<> ');
        if ($messageId === '') {
            return [];
        }

        $severity = $event['severity'] ?? null;

        $status = match ($event['event'] ?? null) {
            'delivered' => NotificationStatus::Delivered,
            'failed' => $severity === 'temporary' ? null : NotificationStatus::Bounced,
            'rejected' => NotificationStatus::Bounced,
            'complained' => NotificationStatus::Complained,
            default => null,
        };

        if ($status === null) {
            return [];
        }

        $error = data_get($event, 'delivery-status.description') ?: data_get($event, 'delivery-status.message') ?: ($event['reason'] ?? null);
        $timestamp = isset($event['timestamp']) ? Carbon::createFromTimestamp((int) $event['timestamp']) : null;

        return [new DeliveryReport($messageId, $status, is_string($error) ? $error : null, $timestamp)];
    }
}
