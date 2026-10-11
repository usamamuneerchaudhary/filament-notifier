<?php

namespace Usamamuneerchaudhary\Notifier\Services;

use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;
use Usamamuneerchaudhary\Notifier\Events\NotificationStatusUpdated;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Services\Webhooks\DeliveryReport;

class DeliveryStatusService
{
    public function __construct(protected NotificationRepository $notifications) {}

    /**
     * Apply a provider delivery report. Late or duplicate reports never move a notification backwards.
     */
    public function apply(DeliveryReport $report): ?Notification
    {
        $notification = $this->notifications->findByProviderMessageId($report->messageId);

        if (! $notification) {
            return null;
        }

        $previous = $notification->statusEnum();
        $occurredAt = $report->occurredAt ?? now();
        $attributes = [];

        $timestampColumn = match ($report->status) {
            NotificationStatus::Delivered => 'delivered_at',
            NotificationStatus::Bounced => 'bounced_at',
            NotificationStatus::Complained => 'complained_at',
            default => null,
        };

        if ($timestampColumn !== null && $notification->{$timestampColumn} === null) {
            $attributes[$timestampColumn] = $occurredAt;
        }

        if ($report->error !== null && in_array($report->status, [NotificationStatus::Bounced, NotificationStatus::Complained], true)) {
            $attributes['error'] = $report->error;
        }

        if ($previous === null || $report->status->rank() > $previous->rank()) {
            $attributes['status'] = $report->status->value;
        }

        if ($attributes !== []) {
            $notification->update($attributes);
        }

        if (isset($attributes['status']) && $previous !== $report->status) {
            NotificationStatusUpdated::dispatch($notification, $previous, $report->status);
        }

        return $notification;
    }
}
