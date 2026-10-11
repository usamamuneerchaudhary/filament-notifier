<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Feature;

use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Tests\TestCase;

class NotificationChannelTest extends TestCase
{
    public function test_it_can_create_a_notification_channel()
    {
        $channel = NotificationChannel::create([
            'title' => 'Email Notifications',
            'type' => 'email',
            'icon' => 'heroicon-o-envelope',
            'is_active' => true,
            'settings' => [
                'smtp_host' => 'smtp.mailtrap.io',
                'smtp_port' => 2525,
            ],
        ]);

        $this->assertDatabaseHas('notifier_channels', [
            'title' => 'Email Notifications',
            'type' => 'email',
            'is_active' => true,
        ]);

        $this->assertEquals('Email Notifications', $channel->title);
        $this->assertEquals('email', $channel->type);
        $this->assertTrue($channel->is_active);
        $this->assertIsArray($channel->settings);
    }

    public function test_it_can_have_many_notifications()
    {
        $channel = NotificationChannel::create([
            'title' => 'Email Notifications',
            'type' => 'email',
            'is_active' => true,
            'settings' => [],
        ]);

        $notification1 = Notification::create([
            'notification_template_id' => 1,
            'user_id' => 1,
            'channel' => 'email',
            'subject' => 'Test Subject 1',
            'content' => 'Test Content 1',
            'status' => 'pending',
        ]);

        $notification2 = Notification::create([
            'notification_template_id' => 1,
            'user_id' => 1,
            'channel' => 'email',
            'subject' => 'Test Subject 2',
            'content' => 'Test Content 2',
            'status' => 'pending',
        ]);

        $this->assertCount(2, $channel->notifications);
        $this->assertEquals('Test Subject 1', $channel->notifications->first()->subject);
    }

    public function test_it_casts_boolean_fields_correctly()
    {
        $channel = NotificationChannel::create([
            'title' => 'Test Channel',
            'type' => 'test',
            'is_active' => 1, // Integer
            'settings' => [],
        ]);

        $this->assertTrue($channel->is_active);
        $this->assertIsBool($channel->is_active);
    }

    public function test_it_casts_settings_as_array()
    {
        $settings = [
            'api_key' => 'test-key',
            'webhook_url' => 'https://example.com/webhook',
        ];

        $channel = NotificationChannel::create([
            'title' => 'Test Channel',
            'type' => 'test',
            'is_active' => true,
            'settings' => $settings,
        ]);

        $this->assertIsArray($channel->settings);
        $this->assertEquals('test-key', $channel->settings['api_key']);
        $this->assertEquals('https://example.com/webhook', $channel->settings['webhook_url']);
    }
}
