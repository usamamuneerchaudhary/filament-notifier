<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;

class EventChannelConfiguration extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Event Channels';

    protected static ?string $title = 'Event Channel Configuration';

    protected static string|null|\UnitEnum $navigationGroup = 'Notifier';

    protected static ?int $navigationSort = 3;

    protected string $view = 'notifier::filament.pages.event-channel-configuration';

    public ?array $data = [];

    public function mount(): void
    {
        $events = NotificationEvent::where('is_active', true)
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $activeChannels = NotificationChannel::where('is_active', true)
            ->orderBy('title')
            ->get();

        $groupedEvents = $events->groupBy('group');

        $formData = [];

        foreach ($groupedEvents as $group => $groupEvents) {
            foreach ($groupEvents as $event) {
                $eventChannels = $event->settings['channels'] ?? [];

                $channelData = [];
                foreach ($activeChannels as $channel) {
                    $channelData[$channel->type] = in_array($channel->type, $eventChannels);
                }

                $formData["event_{$event->id}"] = $channelData;
            }
        }

        $this->form->fill($formData);
    }

    protected function getFormSchema(): array
    {
        $events = NotificationEvent::where('is_active', true)
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $activeChannels = NotificationChannel::where('is_active', true)
            ->orderBy('title')
            ->get();

        $groupedEvents = $events->groupBy('group');

        $sections = [];

        foreach ($groupedEvents as $group => $groupEvents) {
            $fields = [];

            foreach ($groupEvents as $event) {
                $channelCheckboxes = [];

                foreach ($activeChannels as $channel) {
                    $channelCheckboxes[] = Checkbox::make("event_{$event->id}.{$channel->type}")
                        ->label($channel->title)
                        ->inline(false);
                }

                $fields[] = Grid::make([
                    'default' => 1,
                    'md' => $activeChannels->count() + 1,
                ])
                    ->schema([
                        Forms\Components\Placeholder::make("event_label_{$event->id}")
                            ->label($event->name)
                            ->content($event->description ?: '')
                            ->extraAttributes([
                                'class' => 'font-medium text-gray-900',
                            ]),
                        ...$channelCheckboxes,
                    ])
                    ->extraAttributes([
                        'class' => 'border-b border-gray-200 py-4 first:pt-0 last:border-b-0 last:pb-0',
                    ]);
            }

            $sections[] = Section::make($group ?: 'General')
                ->description('Configure which channels should be used for each event. These are the default channels that will be used when sending notifications for these events.')
                ->schema([
                    ...$fields,
                ])
                ->collapsible()
                ->collapsed(false);
        }

        return $sections;
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $activeChannels = NotificationChannel::where('is_active', true)
            ->pluck('type')
            ->toArray();

        $updated = 0;

        foreach ($data as $key => $channels) {
            if (! str_starts_with($key, 'event_')) {
                continue;
            }

            $eventId = (int) str_replace('event_', '', $key);
            $event = NotificationEvent::find($eventId);

            if (! $event) {
                continue;
            }

            $enabledChannels = [];
            foreach ($channels as $channelType => $enabled) {
                if ($enabled && in_array($channelType, $activeChannels)) {
                    $enabledChannels[] = $channelType;
                }
            }

            $settings = $event->settings ?? [];
            $settings['channels'] = $enabledChannels;

            $event->settings = $settings;
            $event->save();

            $updated++;
        }

        Notification::make()
            ->title('Configuration Saved')
            ->body("Successfully updated channel configuration for {$updated} event(s).")
            ->success()
            ->send();
    }
}
