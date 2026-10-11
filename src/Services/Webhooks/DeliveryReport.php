<?php

namespace Usamamuneerchaudhary\Notifier\Services\Webhooks;

use Carbon\CarbonInterface;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;

/**
 * A provider-neutral delivery update parsed from an inbound webhook.
 */
final readonly class DeliveryReport
{
    public function __construct(
        public string $messageId,
        public NotificationStatus $status,
        public ?string $error = null,
        public ?CarbonInterface $occurredAt = null,
    ) {}
}
