<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationEventResource\Pages\CreateNotificationEvent;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationEventResource\Pages\EditNotificationEvent;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationEventResource\Pages\ListNotificationEvents;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationEventResource\Schemas\NotificationEventForm;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationEventResource\Tables\NotificationEventTable;
use Usamamuneerchaudhary\Notifier\Models\NotificationEvent;

class NotificationEventResource extends Resource
{
    protected static ?string $model = NotificationEvent::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|null|\UnitEnum $navigationGroup = 'Notifier';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return NotificationEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationEventTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationEvents::route('/'),
            'create' => CreateNotificationEvent::route('/create'),
            'edit' => EditNotificationEvent::route('/{record}/edit'),
        ];
    }
}
