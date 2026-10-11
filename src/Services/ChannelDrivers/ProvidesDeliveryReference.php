<?php

namespace Usamamuneerchaudhary\Notifier\Services\ChannelDrivers;

/**
 * Optional interface for channel drivers that can report the provider's message id after sending.
 *
 * The id is stored on the notification so inbound delivery webhooks can be matched to it.
 */
interface ProvidesDeliveryReference
{
    public function deliveryReference(): ?string;
}
