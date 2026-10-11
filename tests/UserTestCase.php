<?php

namespace Usamamuneerchaudhary\Notifier\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;
use Usamamuneerchaudhary\Notifier\Models\NotificationTemplate;
use Usamamuneerchaudhary\Notifier\Tests\Fixtures\User;

/**
 * Test case with a real `users` table (and Laravel's `notifications` table) for tests
 * that need Eloquent users, for example to resolve `$notification->user`.
 */
abstract class UserTestCase extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        parent::defineDatabaseMigrations();
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.url', 'https://app.test');
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane'.uniqid().'@example.com',
            'password' => 'secret',
        ], $attributes));
    }

    protected function makeChannel(string $type, array $settings = [], bool $active = true): NotificationChannel
    {
        return NotificationChannel::create([
            'title' => ucfirst($type),
            'type' => $type,
            'is_active' => $active,
            'settings' => $settings,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function makeEvent(string $key = 'order.shipped', array $settings = ['channels' => ['email']]): NotificationEvent
    {
        return NotificationEvent::create([
            'group' => 'Orders',
            'name' => 'Order shipped',
            'key' => $key,
            'is_active' => true,
            'settings' => $settings,
        ]);
    }

    protected function makeTemplate(NotificationEvent $event, string $content = 'Hello {{name}}', string $subject = 'Subject for {{name}}'): NotificationTemplate
    {
        return NotificationTemplate::create([
            'name' => 'template-'.$event->key,
            'event_key' => $event->key,
            'subject' => $subject,
            'content' => $content,
        ]);
    }
}
