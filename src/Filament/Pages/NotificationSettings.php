<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Usamamuneerchaudhary\Notifier\Filament\Widgets\NotificationStatsOverview;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;

class NotificationSettings extends Page
{
    use InteractsWithForms;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|null|\UnitEnum $navigationGroup = 'Notifier';

    protected static ?string $title = 'Notification Settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 1;

    protected string $view = 'notifier::pages.settings';

    public array $data;

    public function mount(): void
    {
        $channels = NotificationChannel::all();
        $channelsData = [];

        foreach ($channels as $channel) {
            $settings = $channel->settings ?? [];
            $channelsData[$channel->type] = array_merge([
                'enabled' => $channel->is_active,
            ], $settings);
        }

        // Load preferences settings
        $preferences = NotificationSetting::get('preferences', config('notifier.settings.preferences', []));
        // Load analytics settings
        $analytics = NotificationSetting::get('analytics', config('notifier.settings.analytics', []));
        // Load rate limiting settings
        $rateLimiting = NotificationSetting::get('rate_limiting', config('notifier.settings.rate_limiting', []));

        $this->form->fill([
            'enabled' => NotificationSetting::get('enabled', true),
            'queue_name' => NotificationSetting::get('queue_name', 'default'),
            'default_channel' => NotificationSetting::get('default_channel', 'email'),
            'channels' => $channelsData,
            'preferences' => [
                'enabled' => $preferences['enabled'] ?? true,
                'default_channels' => $preferences['default_channels'] ?? ['email'],
                'allow_override' => $preferences['allow_override'] ?? true,
            ],
            'analytics' => [
                'enabled' => $analytics['enabled'] ?? true,
                'track_opens' => $analytics['track_opens'] ?? true,
                'track_clicks' => $analytics['track_clicks'] ?? true,
                'retention_days' => $analytics['retention_days'] ?? 90,
            ],
            'rate_limiting' => [
                'enabled' => $rateLimiting['enabled'] ?? true,
                'max_per_minute' => $rateLimiting['max_per_minute'] ?? 60,
                'max_per_hour' => $rateLimiting['max_per_hour'] ?? 1000,
                'max_per_day' => $rateLimiting['max_per_day'] ?? 10000,
                'mode' => $rateLimiting['mode'] ?? 'drop',
                'max_per_user_per_hour' => $rateLimiting['max_per_user_per_hour'] ?? 0,
                'max_per_user_per_day' => $rateLimiting['max_per_user_per_day'] ?? 0,
                'max_per_channel_per_minute' => $rateLimiting['max_per_channel_per_minute'] ?? 0,
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $channels = NotificationChannel::all();
        $tabs = [
            Tab::make('General')
                ->icon('heroicon-o-cog')
                ->schema([
                    Toggle::make('enabled')
                        ->label('Enable Notifications')
                        ->default(true),
                    TextInput::make('queue_name')
                        ->label('Queue Name')
                        ->default('default')
                        ->required(),
                    Select::make('default_channel')
                        ->label('Default Channel')
                        ->options(NotificationChannel::pluck('title', 'type')->toArray())
                        ->required(),
                ]),
            Tab::make('Preferences')
                ->icon('heroicon-o-user-circle')
                ->schema([
                    Section::make('User Preferences')
                        ->description('Configure default user notification preferences')
                        ->schema([
                            Toggle::make('preferences.enabled')
                                ->label('Enable User Preferences')
                                ->default(true),
                            Select::make('preferences.default_channels')
                                ->label('Default Channels')
                                ->multiple()
                                ->options(NotificationChannel::pluck('title', 'type')->toArray())
                                ->default(['email'])
                                ->required(),
                            Toggle::make('preferences.allow_override')
                                ->label('Allow Users to Override Preferences')
                                ->default(true)
                                ->helperText('If enabled, users can customize their notification preferences'),
                        ]),
                ]),
            Tab::make('Analytics')
                ->icon('heroicon-o-chart-bar')
                ->schema([
                    Section::make('Analytics Settings')
                        ->description('Configure notification analytics and tracking')
                        ->schema([
                            Toggle::make('analytics.enabled')
                                ->label('Enable Analytics')
                                ->default(true),
                            Toggle::make('analytics.track_opens')
                                ->label('Track Email Opens')
                                ->default(true)
                                ->visible(fn (Get $get): bool => $get('analytics.enabled')),
                            Toggle::make('analytics.track_clicks')
                                ->label('Track Link Clicks')
                                ->default(true)
                                ->visible(fn (Get $get): bool => $get('analytics.enabled')),
                            TextInput::make('analytics.retention_days')
                                ->label('Data Retention (Days)')
                                ->numeric()
                                ->default(90)
                                ->required()
                                ->visible(fn (Get $get): bool => $get('analytics.enabled')),
                        ]),
                ]),
            Tab::make('Rate Limiting')
                ->icon('heroicon-o-clock')
                ->schema([
                    Section::make('Rate Limiting Settings')
                        ->description('Configure rate limits for notifications to prevent abuse')
                        ->schema([
                            Toggle::make('rate_limiting.enabled')
                                ->label('Enable Rate Limiting')
                                ->default(true),
                            TextInput::make('rate_limiting.max_per_minute')
                                ->label('Max Per Minute')
                                ->numeric()
                                ->default(60)
                                ->required()
                                ->visible(fn (Get $get): bool => $get('rate_limiting.enabled')),
                            TextInput::make('rate_limiting.max_per_hour')
                                ->label('Max Per Hour')
                                ->numeric()
                                ->default(1000)
                                ->required()
                                ->visible(fn (Get $get): bool => $get('rate_limiting.enabled')),
                            TextInput::make('rate_limiting.max_per_day')
                                ->label('Max Per Day')
                                ->numeric()
                                ->default(10000)
                                ->required()
                                ->visible(fn (Get $get): bool => $get('rate_limiting.enabled')),
                            Select::make('rate_limiting.mode')
                                ->label('When a limit is reached')
                                ->options([
                                    'drop' => 'Drop the notification',
                                    'defer' => 'Defer it until the limit resets',
                                ])
                                ->default('drop')
                                ->visible(fn (Get $get): bool => $get('rate_limiting.enabled')),
                            TextInput::make('rate_limiting.max_per_user_per_hour')
                                ->label('Max Per User Per Hour')
                                ->numeric()
                                ->default(0)
                                ->helperText('0 disables this limit')
                                ->visible(fn (Get $get): bool => $get('rate_limiting.enabled')),
                            TextInput::make('rate_limiting.max_per_user_per_day')
                                ->label('Max Per User Per Day')
                                ->numeric()
                                ->default(0)
                                ->helperText('0 disables this limit')
                                ->visible(fn (Get $get): bool => $get('rate_limiting.enabled')),
                            TextInput::make('rate_limiting.max_per_channel_per_minute')
                                ->label('Max Per Channel Per Minute')
                                ->numeric()
                                ->default(0)
                                ->helperText('0 disables this limit')
                                ->visible(fn (Get $get): bool => $get('rate_limiting.enabled')),
                        ]),
                ]),
        ];

        // Dynamically create tabs for each channel
        foreach ($channels as $channel) {
            $tabs[] = Tab::make($channel->title)
                ->icon($channel->icon ?? 'heroicon-o-bell')
                ->schema(self::getChannelSchema($channel));
        }

        return $schema
            ->schema([
                Tabs::make('Settings')
                    ->tabs($tabs)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected static function getChannelSchema(NotificationChannel $channel): array
    {
        $schema = [
            Toggle::make("channels.{$channel->type}.enabled")
                ->label("Enable {$channel->title}"),
        ];

        $fields = match ($channel->type) {
            'email' => [
                TextInput::make("channels.{$channel->type}.from_address")
                    ->label('From Address')
                    ->email()
                    ->required()
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
                TextInput::make("channels.{$channel->type}.from_name")
                    ->label('From Name')
                    ->required()
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
            ],
            'slack' => [
                TextInput::make("channels.{$channel->type}.webhook_url")
                    ->label('Webhook URL')
                    ->url()
                    ->required()
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
                TextInput::make("channels.{$channel->type}.channel")
                    ->label('Slack Channel')
                    ->default('#notifications')
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
                TextInput::make("channels.{$channel->type}.username")
                    ->label('Bot Username')
                    ->default('Notification Bot')
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
            ],
            'sms' => [
                Select::make("channels.{$channel->type}.provider")
                    ->label('SMS Provider')
                    ->options([
                        'twilio' => 'Twilio',
                        'vonage' => 'Vonage (Nexmo)',
                        'generic' => 'Generic API',
                    ])
                    ->required()
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
                TextInput::make("channels.{$channel->type}.twilio_account_sid")
                    ->label('Twilio Account SID')
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled") && $get("channels.{$channel->type}.provider") === 'twilio'),
                TextInput::make("channels.{$channel->type}.twilio_auth_token")
                    ->label('Twilio Auth Token')
                    ->password()
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled") && $get("channels.{$channel->type}.provider") === 'twilio'),
                TextInput::make("channels.{$channel->type}.twilio_phone_number")
                    ->label('Twilio Phone Number')
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled") && $get("channels.{$channel->type}.provider") === 'twilio'),
                TextInput::make("channels.{$channel->type}.api_url")
                    ->label('API URL')
                    ->url()
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled") && $get("channels.{$channel->type}.provider") === 'generic'),
                TextInput::make("channels.{$channel->type}.api_key")
                    ->label('API Key')
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled") && ($get("channels.{$channel->type}.provider") === 'generic' || $get("channels.{$channel->type}.provider") === 'vonage')),
                TextInput::make("channels.{$channel->type}.api_secret")
                    ->label('API Secret')
                    ->password()
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled") && ($get("channels.{$channel->type}.provider") === 'generic' || $get("channels.{$channel->type}.provider") === 'vonage')),
            ],
            'push' => [
                TextInput::make("channels.{$channel->type}.firebase_project_id")
                    ->label('Firebase Project ID')
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
                Textarea::make("channels.{$channel->type}.firebase_service_account")
                    ->label('Firebase Service Account')
                    ->rows(4)
                    ->helperText('Paste the service account JSON, or an absolute path to the JSON file. Uses the FCM HTTP v1 API (the legacy server key is no longer supported).')
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
            ],
            'in_app' => [
                Toggle::make("channels.{$channel->type}.broadcast")
                    ->label('Also broadcast in real time')
                    ->helperText('Requires Laravel broadcasting (Echo) to be configured for your panel.')
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
            ],
            'discord' => [
                TextInput::make("channels.{$channel->type}.webhook_url")
                    ->label('Discord Webhook URL')
                    ->url()
                    ->required()
                    ->helperText('Get this from your Discord server settings > Integrations > Webhooks')
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
                TextInput::make("channels.{$channel->type}.username")
                    ->label('Bot Username')
                    ->helperText('Optional: Custom username for the webhook')
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
                TextInput::make("channels.{$channel->type}.avatar_url")
                    ->label('Avatar URL')
                    ->url()
                    ->helperText('Optional: URL for the webhook avatar')
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
                TextInput::make("channels.{$channel->type}.color")
                    ->label('Embed Color')
                    ->helperText('Optional: Decimal color code for embed (e.g., 3447003 for blue)')
                    ->numeric()
                    ->visible(fn (Get $get): bool => $get("channels.{$channel->type}.enabled")),
            ],
            default => [],
        };

        return array_merge($schema, $fields);
    }

    public function save(): void
    {
        $settings = $this->data;

        NotificationSetting::set('enabled', $settings['enabled'] ?? true, 'general');
        NotificationSetting::set('queue_name', $settings['queue_name'] ?? 'default', 'general');
        NotificationSetting::set('default_channel', $settings['default_channel'] ?? 'email', 'general');

        // Save preferences settings
        if (isset($settings['preferences'])) {
            NotificationSetting::set('preferences', $settings['preferences'], 'preferences');
        }

        // Save analytics settings
        if (isset($settings['analytics'])) {
            NotificationSetting::set('analytics', $settings['analytics'], 'analytics');
        }

        // Save rate limiting settings
        if (isset($settings['rate_limiting'])) {
            NotificationSetting::set('rate_limiting', $settings['rate_limiting'], 'rate_limiting');
        }

        // Save channel settings to channel records
        if (isset($settings['channels']) && is_array($settings['channels'])) {
            foreach ($settings['channels'] as $channelType => $channelData) {
                $channel = NotificationChannel::where('type', $channelType)->first();

                if ($channel) {
                    $isActive = $channelData['enabled'] ?? false;
                    unset($channelData['enabled']);

                    $channel->is_active = $isActive;

                    $existingSettings = $channel->settings ?? [];
                    $channel->settings = array_merge($existingSettings, $channelData);

                    $channel->save();
                }
            }
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            NotificationStatsOverview::class,
        ];
    }
}
