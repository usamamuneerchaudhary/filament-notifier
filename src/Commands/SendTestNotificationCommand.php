<?php

namespace Usamamuneerchaudhary\Notifier\Commands;

use Illuminate\Console\Command;
use Usamamuneerchaudhary\Notifier\Services\NotifierManager;

class SendTestNotificationCommand extends Command
{
    protected $signature = 'notifier:test
                            {event : The event key to trigger}
                            {--user=1 : User ID to send to}
                            {--channel= : Send through a single channel (email, in_app, slack, discord, sms, push). Defaults to all channels configured for the event}
                            {--data=* : Additional data to pass to the notification}';

    protected $description = 'Send a test notification';

    public function handle(NotifierManager $notifier)
    {
        $eventKey = $this->argument('event');
        $userId = $this->option('user');
        $channel = $this->option('channel');
        $data = $this->option('data');

        $notificationData = [];
        foreach ($data as $item) {
            if (str_contains($item, '=')) {
                [$key, $value] = explode('=', $item, 2);
                $notificationData[$key] = $value;
            }
        }

        $userModel = config('auth.providers.users.model');
        $user = $userModel::find($userId);

        if (! $user) {
            $this->error("User with ID {$userId} not found.");

            return 1;
        }

        $this->info('Sending test notification...');
        $this->info("Event: {$eventKey}");
        $this->info("User: {$user->name} ({$user->email})");
        $this->info('Channel: '.($channel ?: 'all configured channels'));

        if (! empty($notificationData)) {
            $this->info('Data: '.json_encode($notificationData));
        }

        try {
            if ($channel) {
                $notifier->sendToChannel($user, $eventKey, $channel, $notificationData);
            } else {
                $notifier->send($user, $eventKey, $notificationData);
            }

            $this->info('✅ Test notification sent successfully!');
            $this->info('Check the notifier_notifications table to see the result.');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Failed to send test notification: '.$e->getMessage());

            return 1;
        }
    }
}
