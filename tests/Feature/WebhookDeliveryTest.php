<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;
use Usamamuneerchaudhary\Notifier\Events\NotificationStatusUpdated;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Tests\UserTestCase;

class WebhookDeliveryTest extends UserTestCase
{
    public function test_it_rejects_an_unknown_provider(): void
    {
        $this->postJson('/notifier/webhooks/carrier-pigeon')->assertNotFound();
    }

    public function test_it_rejects_a_mailgun_webhook_with_a_bad_signature(): void
    {
        config(['notifier.webhooks.mailgun.signing_key' => 'secret']);

        $this->postJson('/notifier/webhooks/mailgun', [
            'signature' => [
                'timestamp' => '1',
                'token' => 'abc',
                'signature' => 'nope',
            ],
        ])->assertForbidden();
    }

    public function test_it_marks_a_notification_delivered_from_mailgun(): void
    {
        Event::fake([NotificationStatusUpdated::class]);
        config(['notifier.webhooks.mailgun.signing_key' => 'secret']);

        $notification = $this->notification('mailgun-message-1');
        $timestamp = (string) time();
        $token = 'tok123';

        $this->postJson('/notifier/webhooks/mailgun', [
            'signature' => [
                'timestamp' => $timestamp,
                'token' => $token,
                'signature' => hash_hmac('sha256', $timestamp.$token, 'secret'),
            ],
            'event-data' => [
                'event' => 'delivered',
                'timestamp' => time(),
                'message' => [
                    'headers' => ['message-id' => 'mailgun-message-1'],
                ],
            ],
        ])->assertOk()->assertJsonPath('processed', 1);

        $this->assertSame(NotificationStatus::Delivered->value, $notification->fresh()->status);
        $this->assertNotNull($notification->fresh()->delivered_at);
        Event::assertDispatched(NotificationStatusUpdated::class);
    }

    public function test_it_does_not_move_a_bounced_notification_back_to_delivered(): void
    {
        config(['notifier.webhooks.mailgun.signing_key' => 'secret']);

        $notification = $this->notification('mailgun-message-2', NotificationStatus::Bounced);

        $timestamp = (string) time();
        $token = 'tok456';

        $this->postJson('/notifier/webhooks/mailgun', [
            'signature' => [
                'timestamp' => $timestamp,
                'token' => $token,
                'signature' => hash_hmac('sha256', $timestamp.$token, 'secret'),
            ],
            'event-data' => [
                'event' => 'delivered',
                'timestamp' => time(),
                'message' => [
                    'headers' => ['message-id' => 'mailgun-message-2'],
                ],
            ],
        ])->assertOk();

        $this->assertSame(NotificationStatus::Bounced->value, $notification->fresh()->status);
    }

    public function test_it_marks_a_notification_from_postmark_basic_auth(): void
    {
        config([
            'notifier.webhooks.postmark.username' => 'hook',
            'notifier.webhooks.postmark.password' => 'secret',
        ]);

        $notification = $this->notification('pm-1');

        $this->call('POST', '/notifier/webhooks/postmark', [
            'RecordType' => 'Delivery',
            'MessageID' => 'pm-1',
            'DeliveredAt' => now()->toIso8601String(),
        ], [], [], [
            'PHP_AUTH_USER' => 'hook',
            'PHP_AUTH_PW' => 'secret',
        ])->assertOk()->assertJsonPath('processed', 1);

        $this->assertSame(NotificationStatus::Delivered->value, $notification->fresh()->status);
    }

    public function test_it_marks_a_twilio_message_delivered(): void
    {
        config(['notifier.webhooks.twilio.auth_token' => 'twilio-secret']);

        $notification = $this->notification('SM123');
        $url = url('/notifier/webhooks/twilio');
        $params = [
            'MessageSid' => 'SM123',
            'MessageStatus' => 'delivered',
        ];
        ksort($params);
        $payload = $url;
        foreach ($params as $key => $value) {
            $payload .= $key.$value;
        }

        $this->call('POST', '/notifier/webhooks/twilio', $params, [], [], [
            'HTTP_X_TWILIO_SIGNATURE' => base64_encode(hash_hmac('sha1', $payload, 'twilio-secret', true)),
        ])->assertOk()->assertJsonPath('processed', 1);

        $this->assertSame(NotificationStatus::Delivered->value, $notification->fresh()->status);
    }

    private function notification(string $messageId, NotificationStatus $status = NotificationStatus::Sent): Notification
    {
        $user = $this->makeUser();
        $event = $this->makeEvent('order.shipped');
        $template = $this->makeTemplate($event);

        return Notification::create([
            'notification_template_id' => $template->id,
            'user_id' => $user->id,
            'channel' => 'email',
            'event_key' => $event->key,
            'subject' => 'Shipped',
            'content' => 'Your order shipped',
            'status' => $status->value,
            'provider_message_id' => $messageId,
        ]);
    }
}
