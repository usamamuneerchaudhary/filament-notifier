<?php

namespace Usamamuneerchaudhary\Notifier\Facades;

use Illuminate\Support\Facades\Facade;
use Usamamuneerchaudhary\Notifier\Services\NotificationRepository;

/**
 * @method \Usamamuneerchaudhary\Notifier\Models\Notification|null findByToken(string $token)
 *
 * @see NotificationRepository
 */
class NotificationRepo extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return NotificationRepository::class;
    }
}
