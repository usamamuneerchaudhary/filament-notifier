<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationChannelResource\Pages\CreateNotificationChannel;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationChannelResource\Pages\EditNotificationChannel;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationChannelResource\Pages\ListNotificationChannels;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationChannelResource\Schemas\NotificationChannelForm;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationChannelResource\Tables\NotificationChannelTable;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;

class NotificationChannelResource extends Resource
{
    protected static ?string $model = NotificationChannel::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-envelope';

    protected static string|null|\UnitEnum $navigationGroup = 'Notifier';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return NotificationChannelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationChannelTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationChannels::route('/'),
            'create' => CreateNotificationChannel::route('/create'),
            'edit' => EditNotificationChannel::route('/{record}/edit'),
        ];
    }
}
