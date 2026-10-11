<?php

namespace Usamamuneerchaudhary\Notifier\Facades;

use Illuminate\Support\Facades\Facade;
use Usamamuneerchaudhary\Notifier\Services\PreferenceService;

/**
 * @method static array getUserPreferences($user, string $eventKey)
 * @method static array getChannelsForEvent(\Usamamuneerchaudhary\Notifier\Models\NotificationEvent $event, ?\Usamamuneerchaudhary\Notifier\Models\NotificationPreference $preference)
 * @method static bool shouldSendToChannel($user, string $channelType, array $preferences)
 *
 * @see PreferenceService
 */
class Preference extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return PreferenceService::class;
    }
}
