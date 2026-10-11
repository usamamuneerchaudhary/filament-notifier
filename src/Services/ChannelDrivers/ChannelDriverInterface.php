<?php

namespace Usamamuneerchaudhary\Notifier\Services\ChannelDrivers;

use Usamamuneerchaudhary\Notifier\Models\Notification;

interface ChannelDriverInterface
{
    /**
     * Send a notification through this channel
     */
    public function send(Notification $notification): bool;

    /**
     * Validate the settings for this channel
     */
    public function validateSettings(array $settings): bool;
}
