<?php

namespace Usamamuneerchaudhary\Notifier\Livewire;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Usamamuneerchaudhary\Notifier\Services\PreferenceService;

/**
 * Lets the signed-in user choose which channels they receive each notification event on.
 *
 * Use it anywhere: `<livewire:notifier-preferences />` or `@livewire('notifier-preferences')`.
 */
class NotificationPreferencesPanel extends Component
{
    /**
     * @var array<int|string, array<string, bool>>
     */
    public array $values = [];

    public bool $canEdit = true;

    public ?string $message = null;

    public bool $messageIsError = false;

    public function mount(PreferenceService $preferenceService): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $this->values = $preferenceService->getPreferenceMatrix($user)['values'];
        $this->canEdit = $preferenceService->allowsOverride();
    }

    public function save(PreferenceService $preferenceService): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        if (! $preferenceService->allowsOverride()) {
            $this->notify('User preference override is disabled by the administrator.', true);

            return;
        }

        $updated = $preferenceService->savePreferenceMatrix($user, $this->values);

        $this->notify("Saved {$updated} notification preference(s).");
    }

    public function render(PreferenceService $preferenceService): View
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $matrix = $preferenceService->getPreferenceMatrix($user);

        return view('notifier::livewire.notification-preferences', [
            'channels' => $matrix['channels'],
            'groups' => $matrix['groups'],
        ]);
    }

    protected function notify(string $message, bool $isError = false): void
    {
        $this->message = $message;
        $this->messageIsError = $isError;

        $toast = FilamentNotification::make()->title($message);
        $isError ? $toast->danger() : $toast->success();
        $toast->send();
    }
}
