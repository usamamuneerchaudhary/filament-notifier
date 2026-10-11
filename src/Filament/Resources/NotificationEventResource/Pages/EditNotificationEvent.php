<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationEventResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationEventResource;

class EditNotificationEvent extends EditRecord
{
    protected static string $resource = NotificationEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
