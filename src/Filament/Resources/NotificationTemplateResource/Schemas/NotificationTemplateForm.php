<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationTemplateResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;

class NotificationTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Information')
                    ->description('Basic information about the notification template')
                    ->schema([
                        TextInput::make('name')
                            ->label('Template Name')
                            ->helperText('A friendly display name for this template (e.g., "Welcome Email", "Order Confirmation"). This is also used as the unique identifier to reference the template in code.')
                            ->placeholder('welcome-email')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('event_key')
                            ->label('Linked Event')
                            ->helperText('Link this template to a specific notification event. This is required and helps organize templates. The template will be used when this event is triggered.')
                            ->options(fn () => NotificationEvent::where('is_active', true)->pluck('name', 'key')->toArray())
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('subject')
                            ->label('Subject Line')
                            ->helperText('The subject line for email notifications. For SMS/Slack/Discord, this may be used as a title. Use {{variable}} for dynamic content.')
                            ->placeholder('Welcome to {{app_name}}, {{name}}!')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Enable or disable this template. Inactive templates will not be used for sending notifications.')
                            ->default(true)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Template Content')
                    ->description('The actual content of the notification template')
                    ->schema([
                        Textarea::make('content')
                            ->label('Template Content')
                            ->helperText('The main content of your notification. Use {{variable}} syntax to insert dynamic values. Example: "Hi {{name}}, welcome to {{app_name}}!"')
                            ->placeholder("Hi {{name}},\n\nWelcome to {{app_name}}! We're excited to have you on board.\n\nBest regards,\nThe {{app_name}} Team")
                            ->required()
                            ->rows(12)
                            ->columnSpanFull(),
                    ]),

                Section::make('Template Variables')
                    ->description('Define the variables that can be used in this template. These help document what data should be passed when sending notifications.')
                    ->schema([
                        KeyValue::make('variables')
                            ->label('Variables')
                            ->keyLabel('Variable Name')
                            ->valueLabel('Description')
                            ->helperText('Document the variables used in your template. Key should match the variable name (without {{}}), value should describe what it represents. Example: name → "User\'s full name", app_name → "Application name"')
                            ->addable(true)
                            ->deletable(true)
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Template Examples')
                    ->description('Example templates for different use cases. Click to expand.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Textarea::make('email_example')
                            ->label('Email Template Example')
                            ->default("Subject: Welcome to {{app_name}}, {{name}}!\n\nContent:\nHi {{name}},\n\nWelcome to {{app_name}}! We're excited to have you on board.\n\nYour account has been created successfully. You can now log in using:\nEmail: {{email}}\n\nBest regards,\nThe {{app_name}} Team")
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(8)
                            ->columnSpanFull(),

                        Textarea::make('sms_example')
                            ->label('SMS Template Example')
                            ->default("Subject: Order Confirmation\n\nContent:\nHi {{name}}, your order #{{order_number}} has been confirmed. Total: {{amount}}. Track at {{tracking_url}}")
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(5)
                            ->columnSpanFull(),

                        Textarea::make('slack_example')
                            ->label('Slack Template Example')
                            ->default("Subject: New Project Created\n\nContent:\n🎉 New project created!\n\nProject: {{project_name}}\nCreated by: {{user_name}}\nView: {{project_url}}")
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(6)
                            ->columnSpanFull(),

                        Textarea::make('discord_example')
                            ->label('Discord Template Example')
                            ->default("Subject: New Project Created\n\nContent:\n🎉 New project created!\n\nProject: {{project_name}}\nCreated by: {{user_name}}\nView: {{project_url}}")
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
