<div>
    <form wire:submit="save" style="display: grid; gap: 1.5rem;">
        @forelse ($groups as $group => $events)
            <x-filament::section :heading="$group" :collapsible="true">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                        <thead>
                            <tr>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 600;">Event</th>
                                @foreach ($channels as $channel)
                                    <th style="padding: 0.5rem 0.75rem; font-weight: 600; text-align: center;">{{ $channel->title }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $event)
                                <tr wire:key="event-{{ $event->id }}" style="border-top: 1px solid rgba(128, 128, 128, 0.25);">
                                    <td style="padding: 0.75rem;">
                                        <div style="font-weight: 500;">{{ $event->name }}</div>
                                        @if ($event->description)
                                            <div style="opacity: 0.7; font-size: 0.8rem;">{{ $event->description }}</div>
                                        @endif
                                    </td>
                                    @foreach ($channels as $channel)
                                        <td style="padding: 0.75rem; text-align: center;">
                                            <x-filament::input.checkbox
                                                wire:model="values.{{ $event->id }}.{{ $channel->type }}"
                                                :disabled="! $canEdit"
                                                :aria-label="$event->name . ' via ' . $channel->title"
                                            />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                There are no notification events to configure yet.
            </x-filament::section>
        @endforelse

        @if ($message)
            <p role="status" style="font-size: 0.875rem; {{ $messageIsError ? 'color: #dc2626;' : 'color: #16a34a;' }}">{{ $message }}</p>
        @endif

        @if ($canEdit && $groups->isNotEmpty())
            <div style="display: flex; justify-content: flex-end;">
                <x-filament::button type="submit">Save Preferences</x-filament::button>
            </div>
        @endif
    </form>
</div>
