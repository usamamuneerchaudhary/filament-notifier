<?php

namespace Usamamuneerchaudhary\Notifier\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;
use Usamamuneerchaudhary\Notifier\Models\Notification;

/**
 * Fired whenever a notification moves to a new delivery status (sent, delivered, bounced, failed, ...).
 */
class NotificationStatusUpdated
{
    use Dispatchable;

    public function __construct(
        public Notification $notification,
        public ?NotificationStatus $previousStatus,
        public NotificationStatus $status,
    ) {}
}
