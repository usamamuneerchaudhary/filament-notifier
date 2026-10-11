<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationChannelResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationChannelResource;

class EditNotificationChannel extends EditRecord
{
    protected static string $resource = NotificationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
