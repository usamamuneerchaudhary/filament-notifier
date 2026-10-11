<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Unit\ChannelDrivers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Services\ChannelDrivers\PushDriver;
use Usamamuneerchaudhary\Notifier\Services\FcmAccessTokenProvider;
use Usamamuneerchaudhary\Notifier\Tests\UserTestCase;

class PushDriverTest extends UserTestCase
{
    public function test_it_sends_through_the_fcm_http_v1_api(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://fcm.googleapis.com/v1/projects/demo/messages:send' => Http::response([
                'name' => 'projects/demo/messages/abc',
            ], 200),
        ]);

        $this->mock(FcmAccessTokenProvider::class, function ($mock): void {
            $mock->shouldReceive('token')->once()->andReturn('ya29.test-token');
        });

        $this->makeChannel('push', [
            'firebase_project_id' => 'demo',
            'firebase_service_account' => [
                'client_email' => 'firebase-adminsdk@demo.iam.gserviceaccount.com',
                'private_key' => 'unused-in-this-test',
            ],
        ]);

        $user = $this->makeUser(['fcm_token' => 'device-token']);
        $event = $this->makeEvent();
        $template = $this->makeTemplate($event);

        $notification = Notification::create([
            'notification_template_id' => $template->id,
            'user_id' => $user->id,
            'channel' => 'push',
            'subject' => 'Hello',
            'content' => 'World',
            'status' => 'pending',
            'data' => ['order_id' => 9],
        ]);

        $driver = new PushDriver;

        $this->assertTrue($driver->send($notification));
        $this->assertSame('projects/demo/messages/abc', $driver->deliveryReference());

        Http::assertSent(fn ($request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/demo/messages:send'
            && $request->hasHeader('Authorization', 'Bearer ya29.test-token')
            && $request['message']['token'] === 'device-token'
            && $request['message']['notification']['title'] === 'Hello');
    }

    public function test_it_rejects_the_legacy_server_key_settings(): void
    {
        $this->makeChannel('push', [
            'firebase_server_key' => 'legacy-key',
        ]);

        $user = $this->makeUser(['fcm_token' => 'device-token']);
        $event = $this->makeEvent();
        $template = $this->makeTemplate($event);

        $notification = Notification::create([
            'notification_template_id' => $template->id,
            'user_id' => $user->id,
            'channel' => 'push',
            'subject' => 'Hello',
            'content' => 'World',
            'status' => 'pending',
        ]);

        $this->assertFalse((new PushDriver)->send($notification));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }
}
