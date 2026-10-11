<?php

namespace Usamamuneerchaudhary\Notifier\Services\Webhooks;

use Illuminate\Http\Request;

interface WebhookHandler
{
    /**
     * Whether the request really comes from the provider (signature or credentials check).
     */
    public function verify(Request $request): bool;

    /**
     * Handle provider handshakes (for example SNS subscription confirmation).
     * Return true when the request was a handshake and carries no delivery updates.
     */
    public function acknowledge(Request $request): bool;

    /**
     * @return array<int, DeliveryReport>
     */
    public function parse(Request $request): array;
}
