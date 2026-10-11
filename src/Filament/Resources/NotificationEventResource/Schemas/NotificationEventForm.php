<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationEventResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NotificationEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event Information')
                    ->description('Basic information about the notification event')
                    ->schema([
                        TextInput::make('group')
                            ->label('Event Group')
                            ->helperText('Organize events into groups (e.g., "Users", "Projects", "Orders", "Files"). This helps categorize events in the admin panel.')
                            ->placeholder('Users')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->label('Event Name')
                            ->helperText('A friendly display name for this event (e.g., "User Registered", "Order Completed", "Project Created")')
                            ->placeholder('User Registered')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('key')
                            ->label('Event Key')
                            ->helperText('Unique identifier used to trigger this event in code. Use dot notation (e.g., user.registered, order.completed, project.created). This key is used when calling Notifier::send($user, "event.key", $data)')
                            ->placeholder('user.registered')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Description')
                            ->helperText('Describe when and why this event is triggered. This helps other developers understand the event\'s purpose.')
                            ->placeholder('Triggered when a new user successfully registers an account')
                            ->maxLength(65535)
                            ->rows(4)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Enable or disable this event. Inactive events will not trigger notifications even if called in code.')
                            ->default(true)
                            ->inline(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Event Configuration')
                    ->description('Configure which channels and templates to use for this event. You can also configure this in config/notifier.php. Note: This requires running the migration to add the settings column.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        KeyValue::make('settings')
                            ->label('Event Settings')
                            ->keyLabel('Setting Name')
                            ->valueLabel('Setting Value')
                            ->helperText('Optional: Configure event-specific settings. Common settings include "channels" (comma separated channel types), "template" (template key) and fallbacks such as "fallback.email" = "sms" (send via SMS if email permanently fails). You can also configure these in config/notifier.php. Note: Run migrations to add the settings column if you see an error.')
                            ->addable(true)
                            ->deletable(true)
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Event Examples')
                    ->description('Common event examples. Click to expand and see how events are structured.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Textarea::make('user_event_example')
                            ->label('User Events Example')
                            ->default("Group: Users\nName: User Registered\nKey: user.registered\nDescription: Triggered when a new user successfully registers an account\n\nGroup: Users\nName: Password Reset\nKey: user.password_reset\nDescription: Triggered when a user requests a password reset")
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(6)
                            ->columnSpanFull(),

                        Textarea::make('order_event_example')
                            ->label('Order Events Example')
                            ->default("Group: Orders\nName: Order Completed\nKey: order.completed\nDescription: Triggered when an order is successfully completed\n\nGroup: Orders\nName: Order Shipped\nKey: order.shipped\nDescription: Triggered when an order is shipped to the customer")
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(6)
                            ->columnSpanFull(),

                        Textarea::make('project_event_example')
                            ->label('Project Events Example')
                            ->default("Group: Projects\nName: Project Created\nKey: project.created\nDescription: Triggered when a new project is created\n\nGroup: Projects\nName: Project Updated\nKey: project.updated\nDescription: Triggered when a project is updated")
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
