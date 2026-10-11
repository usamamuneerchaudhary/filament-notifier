<?php

namespace Usamamuneerchaudhary\Notifier\Services;

use Illuminate\Support\Collection;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;
use Usamamuneerchaudhary\Notifier\Models\NotificationPreference;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;

class PreferenceService
{
    /**
     * Get user preferences for a specific event
     */
    public function getUserPreferences($user, string $eventKey): array
    {
        $event = NotificationEvent::where('key', $eventKey)->first();

        if (! $event) {
            return [];
        }

        $preference = NotificationPreference::where('user_id', $user->id)
            ->where('notification_event_id', $event->id)
            ->first();

        if ($preference && isset($preference->channels)) {
            return $preference->channels;
        }

        return $this->getDefaultPreferences($event);
    }

    /**
     * Get channels configuration for an event (includes all active channels)
     */
    public function getChannelsForEvent(NotificationEvent $event, ?NotificationPreference $preference): array
    {
        if ($preference && isset($preference->channels)) {
            $channels = $preference->channels;
        } else {
            $channels = $this->getDefaultPreferences($event);
        }

        $activeChannels = NotificationChannel::where('is_active', true)
            ->pluck('type')
            ->toArray();

        foreach ($activeChannels as $channelType) {
            if (! isset($channels[$channelType])) {
                $channels[$channelType] = false;
            }
        }

        return $channels;
    }

    /**
     * Get default preferences for an event based on settings
     */
    protected function getDefaultPreferences(NotificationEvent $event): array
    {
        $defaultChannels = NotificationSetting::get(
            'preferences.default_channels',
            config('notifier.settings.preferences.default_channels', ['email'])
        );

        $preferences = [];
        foreach ($defaultChannels as $channel) {
            $preferences[$channel] = true;
        }

        if (isset($event->settings['channels']) && is_array($event->settings['channels'])) {
            foreach ($event->settings['channels'] as $channel) {
                $preferences[$channel] = true;
            }
        }

        return $preferences;
    }

    /**
     * Whether users may change their own preferences (admin setting).
     */
    public function allowsOverride(): bool
    {
        $preferences = NotificationSetting::getPreferences();

        return (bool) ($preferences['allow_override'] ?? config('notifier.settings.preferences.allow_override', true));
    }

    /**
     * Everything needed to render a preferences screen for the user.
     *
     * @return array{channels: Collection<int, NotificationChannel>, groups: Collection<string, Collection<int, NotificationEvent>>, values: array<int, array<string, bool>>}
     */
    public function getPreferenceMatrix($user): array
    {
        $events = NotificationEvent::where('is_active', true)
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $channels = NotificationChannel::where('is_active', true)
            ->orderBy('title')
            ->get();

        $preferences = NotificationPreference::where('user_id', $user->id)
            ->whereIn('notification_event_id', $events->pluck('id'))
            ->get()
            ->keyBy('notification_event_id');

        $values = [];
        foreach ($events as $event) {
            $resolved = $this->getChannelsForEvent($event, $preferences->get($event->id));

            foreach ($channels as $channel) {
                $values[$event->id][$channel->type] = (bool) ($resolved[$channel->type] ?? false);
            }
        }

        return [
            'channels' => $channels,
            'groups' => $events->groupBy(fn (NotificationEvent $event): string => $event->group ?: 'General'),
            'values' => $values,
        ];
    }

    /**
     * Save a full preference matrix ([eventId => [channelType => bool]]) for the user.
     *
     * @param  array<int|string, array<string, mixed>>  $values
     * @return int Number of events updated
     */
    public function savePreferenceMatrix($user, array $values): int
    {
        $activeChannels = NotificationChannel::where('is_active', true)->pluck('type')->all();
        $events = NotificationEvent::where('is_active', true)->get()->keyBy('id');
        $updated = 0;

        foreach ($values as $eventId => $channels) {
            $event = $events->get((int) $eventId);

            if (! $event || ! is_array($channels)) {
                continue;
            }

            $validated = [];
            foreach ($channels as $channelType => $enabled) {
                if (in_array($channelType, $activeChannels, true)) {
                    $validated[$channelType] = (bool) $enabled;
                }
            }

            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'notification_event_id' => $event->id],
                ['channels' => $validated]
            );

            $updated++;
        }

        return $updated;
    }

    /**
     * Switch a single channel on or off for the user and event, leaving their other choices alone.
     */
    public function setChannelEnabled($userId, NotificationEvent $event, string $channelType, bool $enabled): NotificationPreference
    {
        $preference = NotificationPreference::firstOrNew([
            'user_id' => $userId,
            'notification_event_id' => $event->id,
        ]);

        $preference->channels = array_merge($preference->channels ?? [], [$channelType => $enabled]);
        $preference->save();

        return $preference;
    }

    /**
     * Check if notification should be sent to a specific channel
     */
    public function shouldSendToChannel($user, string $channelType, array $preferences): bool
    {
        $channel = NotificationChannel::where('type', $channelType)
            ->where('is_active', true)
            ->first();

        if (! $channel) {
            return false;
        }

        if (isset($preferences[$channelType]) && ! $preferences[$channelType]) {
            return false;
        }

        return true;
    }
}
