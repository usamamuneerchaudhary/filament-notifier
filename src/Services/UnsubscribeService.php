<?php

namespace Usamamuneerchaudhary\Notifier\Services;

use Illuminate\Support\Facades\URL;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;

class UnsubscribeService
{
    public function __construct(protected PreferenceService $preferenceService) {}

    public function isEnabled(): bool
    {
        return (bool) config('notifier.unsubscribe.enabled', true);
    }

    /**
     * Signed, non-expiring unsubscribe URL for the user. Without an event key it unsubscribes the channel everywhere.
     */
    public function urlFor($user, ?string $eventKey = null, string $channel = 'email'): ?string
    {
        if (! $this->isEnabled() || ! isset($user->id)) {
            return null;
        }

        $parameters = ['user' => (string) $user->id, 'channel' => $channel];

        if ($eventKey) {
            $parameters['event'] = $eventKey;
        }

        $path = URL::signedRoute('notifier.unsubscribe', $parameters, null, false);

        return rtrim((string) config('app.url', ''), '/').$path;
    }

    /**
     * Turn the channel off for one event, or for every active event when no key is given.
     * Unsubscribing always works, regardless of the admin "allow override" setting.
     *
     * @return int Number of events updated
     */
    public function unsubscribe($userId, string $channel, ?string $eventKey = null): int
    {
        $events = NotificationEvent::query()
            ->when($eventKey, fn ($query) => $query->where('key', $eventKey), fn ($query) => $query->where('is_active', true))
            ->get();

        foreach ($events as $event) {
            $this->preferenceService->setChannelEnabled($userId, $event, $channel, false);
        }

        return $events->count();
    }

    public function eventFor(?string $eventKey): ?NotificationEvent
    {
        return $eventKey ? NotificationEvent::where('key', $eventKey)->first() : null;
    }
}
