<?php

namespace Usamamuneerchaudhary\Notifier\Services;

use Usamamuneerchaudhary\Notifier\Services\ChannelDrivers\ChannelDriverInterface;
use Usamamuneerchaudhary\Notifier\Services\ChannelDrivers\DiscordDriver;
use Usamamuneerchaudhary\Notifier\Services\ChannelDrivers\EmailDriver;
use Usamamuneerchaudhary\Notifier\Services\ChannelDrivers\InAppDriver;
use Usamamuneerchaudhary\Notifier\Services\ChannelDrivers\PushDriver;
use Usamamuneerchaudhary\Notifier\Services\ChannelDrivers\SlackDriver;
use Usamamuneerchaudhary\Notifier\Services\ChannelDrivers\SmsDriver;

class ChannelDriverFactory
{
    /**
     * Channel type => human readable label
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'email' => 'Email',
        'in_app' => 'In-app (Filament)',
        'slack' => 'Slack',
        'discord' => 'Discord',
        'sms' => 'SMS',
        'push' => 'Push Notification',
    ];

    /**
     * Create a driver instance for the given channel type
     */
    public function create(string $channelType): ?ChannelDriverInterface
    {
        return match ($channelType) {
            'email' => new EmailDriver,
            'in_app' => new InAppDriver,
            'slack' => new SlackDriver,
            'sms' => new SmsDriver,
            'push' => new PushDriver,
            'discord' => new DiscordDriver,
            default => null,
        };
    }

    /**
     * Get all supported channel types
     *
     * @return array<int, string>
     */
    public function getSupportedTypes(): array
    {
        return array_keys(self::TYPES);
    }
}
