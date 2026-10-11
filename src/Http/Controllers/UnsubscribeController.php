<?php

namespace Usamamuneerchaudhary\Notifier\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Services\UnsubscribeService;

/**
 * Signed one-click unsubscribe endpoint.
 *
 * GET shows a confirmation page; POST performs the unsubscribe (this is also what
 * mail clients call for RFC 8058 one-click `List-Unsubscribe-Post` requests).
 */
class UnsubscribeController extends Controller
{
    public function __construct(protected UnsubscribeService $unsubscribeService) {}

    public function show(Request $request, string $user): View
    {
        [$channel, $eventKey] = $this->resolve($request, $user);

        return view('notifier::unsubscribe', [
            'done' => false,
            'channelTitle' => $this->channelTitle($channel),
            'eventName' => $this->unsubscribeService->eventFor($eventKey)?->name,
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, string $user): View|Response
    {
        [$channel, $eventKey] = $this->resolve($request, $user);

        $this->unsubscribeService->unsubscribe($user, $channel, $eventKey);

        Log::info('Notification unsubscribe recorded', ['user_id' => $user, 'channel' => $channel, 'event' => $eventKey]);

        if ($request->has('List-Unsubscribe')) {
            return response('Unsubscribed.', 200);
        }

        return view('notifier::unsubscribe', [
            'done' => true,
            'channelTitle' => $this->channelTitle($channel),
            'eventName' => $this->unsubscribeService->eventFor($eventKey)?->name,
            'action' => null,
        ]);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function resolve(Request $request, string $user): array
    {
        abort_unless($request->hasValidRelativeSignature(), 403);

        $model = config('auth.providers.users.model');
        abort_if(is_string($model) && class_exists($model) && ! $model::query()->whereKey($user)->exists(), 404);

        $channel = (string) $request->query('channel', 'email');
        $eventKey = $request->query('event');

        return [$channel, is_string($eventKey) && $eventKey !== '' ? $eventKey : null];
    }

    protected function channelTitle(string $channel): string
    {
        return NotificationChannel::where('type', $channel)->first()?->title ?? ucfirst($channel);
    }
}
