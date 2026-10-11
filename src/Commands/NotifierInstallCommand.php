<?php

namespace Usamamuneerchaudhary\Notifier\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;
use Usamamuneerchaudhary\Notifier\Models\NotificationTemplate;

class NotifierInstallCommand extends Command
{
    protected $signature = 'notifier:install {--force : Overwrite existing files}';

    protected $description = 'Install the Filament Notifier package';

    public function handle()
    {
        $this->info('Installing Filament Notifier...');

        // Publish config file
        $this->call('vendor:publish', [
            '--provider' => 'Usamamuneerchaudhary\Notifier\NotifierServiceProvider',
            '--force' => $this->option('force'),
        ]);

        // Publish migrations
        $this->call('vendor:publish', [
            '--tag' => 'notifier-migrations',
            '--force' => $this->option('force'),
        ]);

        // Run migrations
        $this->info('Running migrations...');
        $this->call('migrate');

        $this->checkInAppRequirements();

        // Create sample data
        if ($this->confirm('Would you like to create sample notification channels and templates?')) {
            $this->createSampleData();
        }

        $this->info('Filament Notifier has been installed successfully!');
        $this->info('You can now access the notification management panel in your Filament admin.');
    }

    private function checkInAppRequirements(): void
    {
        if (! Schema::hasTable('notifications')) {
            $this->warn('The in-app channel uses Filament database notifications, but the "notifications" table is missing.');
            $this->warn('Run: php artisan make:notifications-table && php artisan migrate');
            $this->warn('Then add ->databaseNotifications() to your Filament panel.');
        }
    }

    private function createSampleData()
    {
        $this->info('Creating sample data...');

        // Create sample channels
        $channels = [
            [
                'title' => 'Email Notifications',
                'type' => 'email',
                'icon' => 'heroicon-o-envelope',
                'is_active' => true,
                'settings' => [
                    'smtp_host' => 'smtp.mailtrap.io',
                    'smtp_port' => 2525,
                ],
            ],
            [
                'title' => 'In-app Notifications',
                'type' => 'in_app',
                'icon' => 'heroicon-o-bell',
                'is_active' => true,
                'settings' => [
                    'broadcast' => false,
                ],
            ],
            [
                'title' => 'Slack Notifications',
                'type' => 'slack',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'is_active' => true,
                'settings' => [
                    'webhook_url' => env('SLACK_WEBHOOK_URL', ''),
                ],
            ],
        ];

        foreach ($channels as $channelData) {
            NotificationChannel::firstOrCreate(
                ['type' => $channelData['type']],
                $channelData
            );
        }

        // Create sample events first
        $events = [
            [
                'group' => 'User Management',
                'name' => 'User Registered',
                'key' => 'user.registered',
                'description' => 'Triggered when a new user registers',
                'is_active' => true,
            ],
            [
                'group' => 'User Management',
                'name' => 'Password Reset Requested',
                'key' => 'password.reset',
                'description' => 'Triggered when a user requests password reset',
                'is_active' => true,
            ],
        ];

        foreach ($events as $eventData) {
            NotificationEvent::firstOrCreate(
                ['key' => $eventData['key']],
                $eventData
            );
        }

        // Create sample templates
        $templates = [
            [
                'name' => 'Welcome Email',
                'event_key' => 'user.registered',
                'subject' => 'Welcome to {{app_name}}, {{name}}!',
                'content' => "Hi {{name}},\n\nWelcome to {{app_name}}! We're excited to have you on board.\n\nBest regards,\nThe {{app_name}} Team",
                'variables' => [
                    'name' => 'User\'s full name',
                    'app_name' => 'Application name',
                ],
            ],
            [
                'name' => 'Password Reset',
                'event_key' => 'password.reset',
                'subject' => 'Reset Your Password',
                'content' => "Hi {{name}},\n\nYou requested a password reset. Click the link below to reset your password:\n\n{{reset_link}}\n\nIf you didn't request this, please ignore this email.\n\nBest regards,\nThe {{app_name}} Team",
                'variables' => [
                    'name' => 'User\'s full name',
                    'reset_link' => 'Password reset link',
                    'app_name' => 'Application name',
                ],
            ],
        ];

        foreach ($templates as $templateData) {
            NotificationTemplate::firstOrCreate(
                ['name' => $templateData['name'], 'event_key' => $templateData['event_key']],
                $templateData
            );
        }

        $this->info('Sample data created successfully!');
    }
}
