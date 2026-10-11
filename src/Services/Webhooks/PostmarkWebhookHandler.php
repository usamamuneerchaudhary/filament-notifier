<?php

namespace Usamamuneerchaudhary\Notifier\Services\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;

class PostmarkWebhookHandler implements WebhookHandler
{
    public function verify(Request $request): bool
    {
        $username = (string) config('notifier.webhooks.postmark.username', '');
        $password = (string) config('notifier.webhooks.postmark.password', '');

        if ($username === '' || $password === '') {
            return false;
        }

        return hash_equals($username, (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword());
    }

    public function acknowledge(Request $request): bool
    {
        return false;
    }

    public function parse(Request $request): array
    {
        $messageId = (string) $request->input('MessageID', '');
        if ($messageId === '') {
            return [];
        }

        $status = match ($request->input('RecordType')) {
            'Delivery' => NotificationStatus::Delivered,
            'Bounce' => NotificationStatus::Bounced,
            'SpamComplaint' => NotificationStatus::Complained,
            default => null,
        };

        if ($status === null) {
            return [];
        }

        $timestamp = $request->input('DeliveredAt') ?? $request->input('BouncedAt');
        $error = $request->input('Description') ?: $request->input('Details');

        return [new DeliveryReport(
            $messageId,
            $status,
            is_string($error) ? $error : null,
            is_string($timestamp) ? Carbon::parse($timestamp) : null,
        )];
    }
}
