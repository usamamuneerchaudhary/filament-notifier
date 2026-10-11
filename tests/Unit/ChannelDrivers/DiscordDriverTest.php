<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Unit\ChannelDrivers;

use Illuminate\Support\Facades\Http;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Services\ChannelDrivers\DiscordDriver;
use Usamamuneerchaudhary\Notifier\Tests\UserTestCase;

class DiscordDriverTest extends UserTestCase
{
    public function test_it_posts_an_embed_to_the_webhook(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response([], 204),
        ]);

        $this->makeChannel('discord', [
            'webhook_url' => 'https://discord.com/api/webhooks/1/abc',
            'username' => 'Notifier',
        ]);

        $user = $this->makeUser();
        $event = $this->makeEvent();
        $template = $this->makeTemplate($event);

        $notification = Notification::create([
            'notification_template_id' => $template->id,
            'user_id' => $user->id,
            'channel' => 'discord',
            'subject' => 'Deployed',
            'content' => 'Release 2.0 is live',
            'status' => 'pending',
        ]);

        $this->assertTrue((new DiscordDriver)->send($notification));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.com/api/webhooks/1/abc'
            && $request['username'] === 'Notifier'
            && $request['embeds'][0]['title'] === 'Deployed');
    }

    public function test_it_fails_without_a_webhook_url(): void
    {
        $this->makeChannel('discord', []);

        $user = $this->makeUser();
        $event = $this->makeEvent();
        $template = $this->makeTemplate($event);

        $notification = Notification::create([
            'notification_template_id' => $template->id,
            'user_id' => $user->id,
            'channel' => 'discord',
            'subject' => 'Deployed',
            'content' => 'Release 2.0 is live',
            'status' => 'pending',
        ]);

        $this->assertFalse((new DiscordDriver)->send($notification));
    }
}
