<?php

namespace Usamamuneerchaudhary\Notifier\Facades;

use Illuminate\Support\Facades\Facade;
use Usamamuneerchaudhary\Notifier\Services\NotifierManager;
use Usamamuneerchaudhary\Notifier\Testing\NotifierFake;

/**
 * @method static void send($user, string $eventKey, array $data = [])
 * @method static void sendNow($user, string $eventKey, array $data = [])
 * @method static void sendToChannel($user, string $eventKey, string $channelType, array $data = [])
 * @method static void schedule($user, string $eventKey, \Carbon\Carbon $scheduledAt, array $data = [])
 * @method static void registerChannel(string $type, $handler)
 * @method static void registerEvent(string $key, array $config)
 * @method static array getRegisteredChannels()
 * @method static array getRegisteredEvents()
 * @method static void retryNotification(\Usamamuneerchaudhary\Notifier\Models\Notification $notification)
 * @method static \Usamamuneerchaudhary\Notifier\Models\Notification|null dispatchFallback(\Usamamuneerchaudhary\Notifier\Models\Notification $failed)
 *
 * @see NotifierManager
 */
class Notifier extends Facade
{
    /**
     * Replace the notifier with a fake that records sends, for testing.
     */
    public static function fake(): NotifierFake
    {
        $fake = new NotifierFake;

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor()
    {
        return 'notifier';
    }
}
