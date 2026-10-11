<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationResource;
use Usamamuneerchaudhary\Notifier\Services\NotifierManager;

class ViewNotification extends ViewRecord
{
    protected static string $resource = NotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('retry')
                ->label('Retry')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canBeRetried())
                ->action(function () {
                    app(NotifierManager::class)->retryNotification($this->record);

                    Notification::make()
                        ->title('Notification queued for resending')
                        ->success()
                        ->send();
                }),
        ];
    }
}
