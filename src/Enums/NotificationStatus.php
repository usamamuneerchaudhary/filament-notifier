<?php

namespace Usamamuneerchaudhary\Notifier\Enums;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sending => 'Sending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Bounced => 'Bounced',
            self::Complained => 'Complained',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sending => 'info',
            self::Sent => 'primary',
            self::Delivered => 'success',
            self::Bounced, self::Failed => 'danger',
            self::Complained => 'gray',
        };
    }

    /**
     * Statuses that mean the provider accepted the notification at some point.
     *
     * @return array<int, self>
     */
    public static function dispatched(): array
    {
        return [self::Sent, self::Delivered, self::Bounced, self::Complained];
    }

    /**
     * Statuses a notification can be manually retried from.
     *
     * @return array<int, self>
     */
    public static function retryable(): array
    {
        return [self::Failed];
    }

    /**
     * Ordering used by provider webhooks so a late "delivered" never overwrites a "bounced".
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Sending => 1,
            self::Failed => 1,
            self::Sent => 2,
            self::Delivered => 3,
            self::Bounced => 4,
            self::Complained => 5,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function dispatchedValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::dispatched());
    }
}
