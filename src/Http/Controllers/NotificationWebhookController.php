<?php

namespace Usamamuneerchaudhary\Notifier\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Usamamuneerchaudhary\Notifier\Services\DeliveryStatusService;
use Usamamuneerchaudhary\Notifier\Services\Webhooks\WebhookRegistry;

class NotificationWebhookController extends Controller
{
    public function __construct(
        protected WebhookRegistry $registry,
        protected DeliveryStatusService $deliveryStatus
    ) {}

    /**
     * Receive a delivery webhook (delivered, bounced, complained) from a provider.
     */
    public function handle(Request $request, string $provider): JsonResponse
    {
        $handler = $this->registry->handlerFor($provider);

        if (! $handler) {
            return response()->json(['message' => 'Unknown provider.'], 404);
        }

        if (! $handler->verify($request)) {
            Log::warning("Rejected {$provider} delivery webhook: verification failed.");

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        if ($handler->acknowledge($request)) {
            return response()->json(['message' => 'Acknowledged.']);
        }

        $processed = 0;

        foreach ($handler->parse($request) as $report) {
            if ($this->deliveryStatus->apply($report)) {
                $processed++;
            }
        }

        return response()->json(['processed' => $processed]);
    }
}
