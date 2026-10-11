<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationTemplateResource\Pages\CreateNotificationTemplate;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationTemplateResource\Pages\EditNotificationTemplate;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationTemplateResource\Pages\ListNotificationTemplates;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationTemplateResource\Schemas\NotificationTemplateForm;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationTemplateResource\Tables\NotificationTemplateTable;
use Usamamuneerchaudhary\Notifier\Models\NotificationTemplate;

class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-text';

    protected static string|null|\UnitEnum $navigationGroup = 'Notifier';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return NotificationTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationTemplateTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationTemplates::route('/'),
            'create' => CreateNotificationTemplate::route('/create'),
            'edit' => EditNotificationTemplate::route('/{record}/edit'),
        ];
    }
}
