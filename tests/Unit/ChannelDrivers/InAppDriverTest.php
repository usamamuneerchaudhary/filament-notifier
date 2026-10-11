<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Unit\ChannelDrivers;

use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Services\ChannelDrivers\InAppDriver;
use Usamamuneerchaudhary\Notifier\Tests\UserTestCase;

class InAppDriverTest extends UserTestCase
{
    public function test_it_writes_a_filament_database_notification(): void
    {
        $this->makeChannel('in_app');

        $user = $this->makeUser();
        $event = $this->makeEvent();
        $template = $this->makeTemplate($event);

        $notification = Notification::create([
            'notification_template_id' => $template->id,
            'user_id' => $user->id,
            'channel' => 'in_app',
            'subject' => 'Invoice paid',
            'content' => 'Invoice #12 was paid',
            'status' => 'pending',
            'data' => [
                'action_url' => 'https://app.test/invoices/12',
                'action_label' => 'View invoice',
                'status' => 'success',
            ],
        ]);

        $this->assertTrue((new InAppDriver)->send($notification));
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => $user::class,
        ]);
    }

    public function test_it_fails_when_the_user_cannot_be_notified(): void
    {
        $this->makeChannel('in_app');

        $event = $this->makeEvent();
        $template = $this->makeTemplate($event);

        $notification = Notification::create([
            'notification_template_id' => $template->id,
            'user_id' => 999,
            'channel' => 'in_app',
            'subject' => 'Missing user',
            'content' => 'Nobody home',
            'status' => 'pending',
        ]);

        $this->assertFalse((new InAppDriver)->send($notification));
    }
}
