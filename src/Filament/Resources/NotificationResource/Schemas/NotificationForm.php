<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationResource\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;
use Usamamuneerchaudhary\Notifier\Services\ChannelDriverFactory;

class NotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('notification_template_id')
                            ->relationship('template', 'name')
                            ->required()
                            ->searchable(),
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable(),
                        Select::make('channel')
                            ->options(ChannelDriverFactory::TYPES)
                            ->required(),
                        TextInput::make('subject')
                            ->maxLength(255),
                        Textarea::make('content')
                            ->rows(10)
                            ->readonly(),
                        KeyValue::make('data')
                            ->keyLabel('Key')
                            ->valueLabel('Value'),
                        Select::make('status')
                            ->options(NotificationStatus::options())
                            ->required(),
                        DateTimePicker::make('scheduled_at')
                            ->readonly(),
                        DateTimePicker::make('sent_at')
                            ->readonly(),
                        Textarea::make('error')
                            ->rows(3)
                            ->readonly()
                            ->visible(fn ($record) => $record && filled($record->error)),
                    ]),
                Section::make('Delivery timeline')
                    ->description('Provider delivery webhooks fill in the delivered, bounced and complained times.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('attempts')
                            ->numeric()
                            ->readonly(),
                        DateTimePicker::make('next_retry_at')
                            ->label('Next retry at')
                            ->readonly(),
                        TextInput::make('provider_message_id')
                            ->label('Provider message ID')
                            ->readonly(),
                        DateTimePicker::make('delivered_at')
                            ->readonly(),
                        DateTimePicker::make('opened_at')
                            ->readonly(),
                        DateTimePicker::make('clicked_at')
                            ->readonly(),
                        DateTimePicker::make('bounced_at')
                            ->readonly(),
                        DateTimePicker::make('complained_at')
                            ->readonly(),
                    ]),
            ]);
    }
}
