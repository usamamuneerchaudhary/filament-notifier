<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;
use Usamamuneerchaudhary\Notifier\Models\NotificationTemplate;
use Usamamuneerchaudhary\Notifier\Services\UrlTrackingService;
use Usamamuneerchaudhary\Notifier\Tests\TestCase;

class SignedTrackingRedirectTest extends TestCase
{
    public function test_it_follows_a_signed_external_url(): void
    {
        $notification = $this->createNotificationWithToken('signed-token');
        Cache::put('notifier:tracking_token:signed-token', $notification->id, now()->addDays(30));

        $signedUrl = app(UrlTrackingService::class)->trackingUrl('signed-token', 'https://example.com/welcome');

        $this->get($signedUrl)->assertRedirect('https://example.com/welcome');
    }

    public function test_it_does_not_follow_an_unsigned_external_url(): void
    {
        $notification = $this->createNotificationWithToken('unsigned-token');
        Cache::put('notifier:tracking_token:unsigned-token', $notification->id, now()->addDays(30));

        $this->get('/notifier/track/click/unsigned-token?url='.urlencode('https://evil.example/phish'))
            ->assertRedirect('/');
    }

    public function test_it_follows_an_unsigned_url_that_was_in_the_notification(): void
    {
        $url = 'https://example.com/docs';
        $notification = $this->createNotificationWithToken('linked-token', 'Read <a href="'.app(UrlTrackingService::class)->trackingUrl('linked-token', $url).'">docs</a>');
        Cache::put('notifier:tracking_token:linked-token', $notification->id, now()->addDays(30));

        $this->get('/notifier/track/click/linked-token?url='.urlencode($url))
            ->assertRedirect($url);
    }

    public function test_it_follows_an_allowed_host_without_a_signature(): void
    {
        config(['notifier.tracking.allowed_hosts' => ['trusted.test'], 'app.url' => 'https://app.test']);

        $notification = $this->createNotificationWithToken('host-token');
        Cache::put('notifier:tracking_token:host-token', $notification->id, now()->addDays(30));

        $this->get('/notifier/track/click/host-token?url='.urlencode('https://trusted.test/page'))
            ->assertRedirect('https://trusted.test/page');
    }

    private function createNotificationWithToken(string $token, string $content = 'Test'): Notification
    {
        NotificationChannel::create([
            'title' => 'Email',
            'type' => 'email',
            'is_active' => true,
        ]);

        $event = NotificationEvent::create([
            'group' => 'Test',
            'name' => 'Test Event',
            'key' => 'test.event.'.$token,
            'is_active' => true,
        ]);

        $template = NotificationTemplate::create([
            'name' => 'template-'.$token,
            'event_key' => $event->key,
            'subject' => 'Test',
            'content' => $content,
        ]);

        return Notification::create([
            'notification_template_id' => $template->id,
            'user_id' => 1,
            'channel' => 'email',
            'subject' => 'Test',
            'content' => $content,
            'status' => 'sent',
            'tracking_token' => $token,
            'data' => ['tracking_token' => $token],
        ]);
    }
}
