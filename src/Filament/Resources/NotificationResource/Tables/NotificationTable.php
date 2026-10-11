<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Resources\NotificationResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Usamamuneerchaudhary\Notifier\Enums\NotificationStatus;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Services\ChannelDriverFactory;
use Usamamuneerchaudhary\Notifier\Services\NotifierManager;

class NotificationTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => ChannelDriverFactory::TYPES[$state] ?? $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'email' => 'primary',
                        'sms' => 'success',
                        'slack' => 'warning',
                        'push' => 'danger',
                        'in_app' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('subject')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => NotificationStatus::tryFrom((string) $state)?->label() ?? $state)
                    ->color(fn (?string $state): string => NotificationStatus::tryFrom((string) $state)?->color() ?? 'gray')
                    ->description(fn (Notification $record): ?string => $record->attempts > 1 ? "{$record->attempts} attempts" : null),
                TextColumn::make('delivered_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('scheduled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->options(ChannelDriverFactory::TYPES),
                SelectFilter::make('status')
                    ->multiple()
                    ->options(NotificationStatus::options()),
                Filter::make('scheduled_at')
                    ->form([
                        DatePicker::make('scheduled_from'),
                        DatePicker::make('scheduled_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['scheduled_from'],
                                fn ($query) => $query->whereDate('scheduled_at', '>=', $data['scheduled_from'])
                            )
                            ->when(
                                $data['scheduled_until'],
                                fn ($query) => $query->whereDate('scheduled_at', '<=', $data['scheduled_until'])
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Notification $record): bool => $record->canBeRetried())
                    ->action(function (Notification $record): void {
                        app(NotifierManager::class)->retryNotification($record);

                        FilamentNotification::make()
                            ->title('Notification queued for retry')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('retryFailed')
                        ->label('Retry failed')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $retryable = $records->filter(fn (Notification $record): bool => $record->canBeRetried());

                            $retryable->each(fn (Notification $record) => app(NotifierManager::class)->retryNotification($record));

                            FilamentNotification::make()
                                ->title($retryable->count().' notification(s) queued for retry')
                                ->body($records->count() > $retryable->count() ? ($records->count() - $retryable->count()).' skipped because they did not fail.' : null)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
