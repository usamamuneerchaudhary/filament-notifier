<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationTemplateResource;

class EditNotificationTemplate extends EditRecord
{
    protected static string $resource = NotificationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
