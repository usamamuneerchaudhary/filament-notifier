<?php

namespace Usamamuneerchaudhary\Notifier\Filament\Pages;

use Filament\Pages\Page;

/**
 * Self-service page where each user manages their own notification preferences.
 * The form itself is the reusable `notifier-preferences` Livewire component.
 */
class NotificationPreferences extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationLabel = 'Notification Preferences';

    protected static ?string $title = 'Notification Preferences';

    protected static string|null|\UnitEnum $navigationGroup = 'Notifier';

    protected static ?int $navigationSort = 6;

    protected string $view = 'notifier::filament.pages.notification-preferences';
}
