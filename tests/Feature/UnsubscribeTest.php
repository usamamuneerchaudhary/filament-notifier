<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationPreference;
use Usamamuneerchaudhary\Notifier\Services\UnsubscribeService;
use Usamamuneerchaudhary\Notifier\Tests\UserTestCase;

class UnsubscribeTest extends UserTestCase
{
    public function test_it_shows_the_signed_unsubscribe_page(): void
    {
        $user = $this->makeUser();
        $this->makeEvent('user.registered');

        $url = app(UnsubscribeService::class)->urlFor($user, 'user.registered');

        $this->get($url)
            ->assertOk()
            ->assertSee('Unsubscribe')
            ->assertSee('Order shipped')
            ->assertSee('Confirm unsubscribe');
    }

    public function test_it_rejects_an_unsigned_unsubscribe_request(): void
    {
        $user = $this->makeUser();

        $this->get('/notifier/unsubscribe/'.$user->id.'?event=user.registered&channel=email')
            ->assertForbidden();
    }

    public function test_it_unsubscribes_the_user_from_the_event(): void
    {
        $user = $this->makeUser();
        $this->makeChannel('email');
        $event = $this->makeEvent('user.registered');
        $url = app(UnsubscribeService::class)->urlFor($user, 'user.registered');

        $this->post($url)->assertOk()->assertSee('You have been unsubscribed');

        $preference = NotificationPreference::where('user_id', $user->id)
            ->where('notification_event_id', $event->id)
            ->first();

        $this->assertNotNull($preference);
        $this->assertFalse($preference->channels['email']);
    }

    public function test_it_returns_plain_text_for_one_click_list_unsubscribe(): void
    {
        $user = $this->makeUser();
        $this->makeChannel('email');
        $this->makeEvent('user.registered');
        $url = app(UnsubscribeService::class)->urlFor($user, 'user.registered');

        $this->post($url, ['List-Unsubscribe' => 'One-Click'])
            ->assertOk()
            ->assertSee('Unsubscribed.');
    }

    public function test_it_injects_an_unsubscribe_url_and_headers_on_email_send(): void
    {
        Mail::fake();
        Queue::fake();

        $user = $this->makeUser();
        $this->makeChannel('email', ['from_address' => 'noreply@example.com']);
        $event = $this->makeEvent('user.registered', ['channels' => ['email']]);
        $this->makeTemplate($event, 'Click {{unsubscribe_url}} to leave.');

        app('notifier')->send($user, 'user.registered');

        $notification = Notification::first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('/notifier/unsubscribe/'.$user->id, $notification->content);
        $this->assertStringContainsString('signature=', $notification->content);
    }
}
