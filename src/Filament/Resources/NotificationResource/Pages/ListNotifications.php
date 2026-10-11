<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Log;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationResource;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;
use Usamamuneerchaudhary\Notifier\Services\NotifierManager;

class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send_test')
                ->label('Send Test Notification')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->form([
                    Select::make('event_key')
                        ->label('Event')
                        ->helperText('Select the notification event to trigger. Make sure the event has a template linked to it.')
                        ->options(function () {
                            return NotificationEvent::where('is_active', true)
                                ->with('templates')
                                ->get()
                                ->mapWithKeys(function ($event) {
                                    $hasTemplate = $event->templates()->exists();
                                    $label = $event->name;
                                    if (! $hasTemplate) {
                                        $label .= ' (⚠️ No template)';
                                    }

                                    return [$event->key => $label];
                                })
                                ->toArray();
                        })
                        ->required()
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            // Load template variables for the selected event
                            if ($state) {
                                $event = NotificationEvent::where('key', $state)->first();
                                if ($event) {
                                    $template = $event->templates()->first();
                                    if ($template && $template->variables) {

                                        $defaultData = [];
                                        foreach ($template->variables as $key => $description) {
                                            $defaultData[$key] = '';
                                        }
                                        $set('data', $defaultData);
                                    }
                                }
                            }
                        }),

                    Select::make('user_id')
                        ->label('User')
                        ->helperText('Select the user to send the notification to')
                        ->options(function () {
                            $userModel = config('auth.providers.users.model');

                            return $userModel::whereNotNull('email')
                                ->get()
                                ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} ({$user->email})"])
                                ->toArray();
                        })
                        ->required()
                        ->searchable()
                        ->preload(),

                    KeyValue::make('data')
                        ->label('Template Data')
                        ->helperText('Enter values for template variables. These will replace {{variable}} placeholders in the template.')
                        ->keyLabel('Variable Name')
                        ->valueLabel('Value')
                        ->addable(true)
                        ->deletable(true)
                        ->reorderable(),

                    Toggle::make('test_all_channels')
                        ->label('Test All Available Channels')
                        ->helperText('If enabled, the notification will be sent to all active channels instead of just the event\'s configured channels. Useful for testing channel configurations.')
                        ->default(false)
                        ->reactive(),

                    Select::make('channels')
                        ->label('Select Channels (if testing all)')
                        ->helperText('Select which channels to test. Only shown when "Test All Available Channels" is enabled.')
                        ->options(function () {
                            return NotificationChannel::where('is_active', true)
                                ->pluck('title', 'type')
                                ->toArray();
                        })
                        ->multiple()
                        ->searchable()
                        ->visible(fn (Get $get) => $get('test_all_channels') === true),
                ])
                ->action(function (array $data) {
                    try {
                        $userModel = config('auth.providers.users.model');
                        $user = $userModel::find($data['user_id']);

                        if (! $user) {
                            throw new \Exception('User not found');
                        }

                        // Check if event has a template
                        $event = NotificationEvent::where('key', $data['event_key'])->first();
                        if (! $event) {
                            throw new \Exception("Event '{$data['event_key']}' not found");
                        }

                        $template = $event->templates()->first();
                        if (! $template) {
                            throw new \Exception("Event '{$event->name}' does not have a template linked to it. Please create a template and link it to this event.");
                        }

                        $notifier = app(NotifierManager::class);
                        $eventKey = $data['event_key'];
                        $templateData = $data['data'] ?? [];

                        $channelsUsed = [];

                        // Check if testing all channels
                        if (! empty($data['test_all_channels'])) {
                            // Get selected channels or all active channels
                            $selectedChannels = $data['channels'] ?? [];

                            if (empty($selectedChannels)) {
                                $allChannels = NotificationChannel::where('is_active', true)->pluck('type')->toArray();
                                $selectedChannels = $allChannels;
                            }

                            foreach ($selectedChannels as $channelType) {
                                $notifier->sendToChannel($user, $eventKey, $channelType, $templateData);
                                $channelsUsed[] = $channelType;
                            }

                            $channelsList = implode(', ', $channelsUsed);
                            $message = "Test notifications queued for {$user->name} ({$user->email}) using event: {$event->name}. Channels: {$channelsList}";
                        } else {
                            $notifier->send($user, $eventKey, $templateData);
                            $message = "Notification queued for {$user->name} ({$user->email}) using event: {$event->name}";
                        }

                        Notification::make()
                            ->title('Test notification sent successfully!')
                            ->body($message.'. Check the notifications list below to see the status.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('Failed to send test notification: '.$e->getMessage(), [
                            'event_key' => $data['event_key'] ?? null,
                            'user_id' => $data['user_id'] ?? null,
                            'trace' => $e->getTraceAsString(),
                        ]);

                        Notification::make()
                            ->title('Failed to send test notification')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
