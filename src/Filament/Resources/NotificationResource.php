<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationResource\Pages\ListNotifications;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationResource\Pages\ViewNotification;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationResource\Schemas\NotificationForm;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationResource\Tables\NotificationTable;
use Usamamuneerchaudhary\Notifier\Models\Notification;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-bell';

    protected static string|null|\UnitEnum $navigationGroup = 'Notifier';

    protected static ?string $modelLabel = 'Notification';

    protected static ?string $pluralModelLabel = 'Notifications';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return NotificationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotifications::route('/'),
            'view' => ViewNotification::route('/{record}'),
        ];
    }
}
