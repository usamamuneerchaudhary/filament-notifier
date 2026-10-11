<?php

namespace Usamamuneerchaudhary\Notifier\Services\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;

/**
 * Amazon SES events delivered through an SNS HTTP(S) subscription.
 */
class SesWebhookHandler implements WebhookHandler
{
    public function verify(Request $request): bool
    {
        $message = $this->payload($request);

        if ($message === null || ! $this->isTopicAllowed($message)) {
            return false;
        }

        $certificateUrl = (string) ($message['SigningCertURL'] ?? '');
        $signature = base64_decode((string) ($message['Signature'] ?? ''), true);

        if ($signature === false || ! $this->isTrustedCertificateUrl($certificateUrl)) {
            return false;
        }

        $stringToSign = $this->stringToSign($message);
        if ($stringToSign === null) {
            return false;
        }

        $certificate = Cache::remember(
            'notifier:sns_certificate:'.md5($certificateUrl),
            now()->addDay(),
            fn (): string => (string) Http::connectTimeout(3)->timeout(10)->get($certificateUrl)->body()
        );

        $publicKey = openssl_pkey_get_public($certificate);
        if ($publicKey === false) {
            return false;
        }

        $algorithm = ($message['SignatureVersion'] ?? '1') === '2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;

        return openssl_verify($stringToSign, $signature, $publicKey, $algorithm) === 1;
    }

    public function acknowledge(Request $request): bool
    {
        $message = $this->payload($request);

        if ($message === null || ($message['Type'] ?? null) !== 'SubscriptionConfirmation') {
            return false;
        }

        $subscribeUrl = (string) ($message['SubscribeURL'] ?? '');

        if ($this->isTrustedCertificateUrl($subscribeUrl)) {
            Http::connectTimeout(3)->timeout(10)->get($subscribeUrl);
        }

        return true;
    }

    public function parse(Request $request): array
    {
        $message = $this->payload($request);

        if ($message === null || ($message['Type'] ?? null) !== 'Notification') {
            return [];
        }

        $event = json_decode((string) ($message['Message'] ?? ''), true);
        if (! is_array($event)) {
            return [];
        }

        $messageId = (string) data_get($event, 'mail.messageId', '');
        if ($messageId === '') {
            return [];
        }

        $type = $event['eventType'] ?? $event['notificationType'] ?? null;

        [$status, $error] = match ($type) {
            'Delivery' => [NotificationStatus::Delivered, null],
            'Bounce' => data_get($event, 'bounce.bounceType') === 'Permanent'
                ? [NotificationStatus::Bounced, (string) data_get($event, 'bounce.bouncedRecipients.0.diagnosticCode', 'Permanent bounce')]
                : [null, null],
            'Complaint' => [NotificationStatus::Complained, null],
            default => [null, null],
        };

        if ($status === null) {
            return [];
        }

        $timestamp = $message['Timestamp'] ?? null;

        return [new DeliveryReport($messageId, $status, $error ?: null, is_string($timestamp) ? Carbon::parse($timestamp) : null)];
    }

    /**
     * SNS posts JSON with a text/plain content type, so decode the raw body.
     *
     * @return array<string, mixed>|null
     */
    protected function payload(Request $request): ?array
    {
        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function isTopicAllowed(array $message): bool
    {
        $allowed = (array) config('notifier.webhooks.ses.topic_arns', []);

        return $allowed === [] || in_array($message['TopicArn'] ?? null, $allowed, true);
    }

    protected function isTrustedCertificateUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && isset($parts['host'])
            && preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/i', $parts['host']) === 1;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function stringToSign(array $message): ?string
    {
        $keys = match ($message['Type'] ?? null) {
            'Notification' => ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'],
            'SubscriptionConfirmation', 'UnsubscribeConfirmation' => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
            default => null,
        };

        if ($keys === null) {
            return null;
        }

        $string = '';
        foreach ($keys as $key) {
            if (isset($message[$key])) {
                $string .= $key."\n".$message[$key]."\n";
            }
        }

        return $string;
    }
}
