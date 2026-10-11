<?php

namespace Usamamuneerchaudhary\Notifier\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Usamamuneerchaudhary\Notifier\Http\Requests\UpdatePreferenceRequest;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;
use Usamamuneerchaudhary\Notifier\Models\NotificationPreference;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;
use Usamamuneerchaudhary\Notifier\Services\PreferenceService;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        protected PreferenceService $preferenceService
    ) {}

    /**
     * Get all notification preferences for the authenticated user
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $events = NotificationEvent::where('is_active', true)->get();
        $preferences = [];

        foreach ($events as $event) {
            $preference = NotificationPreference::where('user_id', $user->id)
                ->where('notification_event_id', $event->id)
                ->first();

            $channels = $this->preferenceService->getChannelsForEvent($event, $preference);

            $preferences[] = [
                'event_key' => $event->key,
                'event_name' => $event->name,
                'event_group' => $event->group,
                'description' => $event->description,
                'channels' => $channels,
            ];
        }

        return response()->json(['data' => $preferences]);
    }

    /**
     * Get available events and channels for building UI
     */
    public function available(): JsonResponse
    {
        $events = NotificationEvent::where('is_active', true)
            ->select('id', 'key', 'name', 'group', 'description', 'settings')
            ->get()
            ->map(function ($event) {
                return [
                    'key' => $event->key,
                    'name' => $event->name,
                    'group' => $event->group,
                    'description' => $event->description,
                    'default_channels' => $event->settings['channels'] ?? [],
                ];
            });

        $channels = NotificationChannel::where('is_active', true)
            ->select('type', 'title', 'icon')
            ->get()
            ->map(function ($channel) {
                return [
                    'type' => $channel->type,
                    'title' => $channel->title,
                    'icon' => $channel->icon,
                ];
            });

        return response()->json([
            'data' => [
                'events' => $events,
                'channels' => $channels,
            ],
        ]);
    }

    /**
     * Get preferences for a specific event
     */
    public function show(string $eventKey): JsonResponse
    {
        $user = Auth::user();
        $event = NotificationEvent::where('key', $eventKey)
            ->where('is_active', true)
            ->firstOrFail();

        $preference = NotificationPreference::where('user_id', $user->id)
            ->where('notification_event_id', $event->id)
            ->first();

        $channels = $this->preferenceService->getChannelsForEvent($event, $preference);

        return response()->json([
            'data' => [
                'event_key' => $event->key,
                'event_name' => $event->name,
                'event_group' => $event->group,
                'description' => $event->description,
                'channels' => $channels,
            ],
        ]);
    }

    /**
     * Update preferences for a specific event
     */
    public function update(UpdatePreferenceRequest $request, string $eventKey): JsonResponse
    {
        $user = Auth::user();
        $event = NotificationEvent::where('key', $eventKey)
            ->where('is_active', true)
            ->firstOrFail();

        $preferences = NotificationSetting::getPreferences();
        if (! ($preferences['allow_override'] ?? config('notifier.settings.preferences.allow_override', true))) {
            return response()->json([
                'message' => 'User preference override is disabled by administrator.',
            ], 403);
        }

        $validatedChannels = $request->validated()['channels'];
        $activeChannels = NotificationChannel::where('is_active', true)
            ->pluck('type')
            ->toArray();

        foreach (array_keys($validatedChannels) as $channelType) {
            if (! in_array($channelType, $activeChannels)) {
                return response()->json([
                    'message' => "Channel '{$channelType}' is not available or active.",
                ], 422);
            }
        }

        $preference = NotificationPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'notification_event_id' => $event->id,
            ],
            [
                'channels' => $validatedChannels,
                'settings' => $request->input('settings', []),
            ]
        );

        return response()->json([
            'data' => [
                'event_key' => $event->key,
                'event_name' => $event->name,
                'channels' => $preference->channels,
            ],
            'message' => 'Preferences updated successfully.',
        ]);
    }
}
