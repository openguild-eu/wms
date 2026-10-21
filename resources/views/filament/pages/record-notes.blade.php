<x-filament-panels::page>
    @php
        $grouped = $this->getBoardMessages()->groupBy(fn ($note) => $note->created_at->toDateString());
    @endphp

    <style>
        .notes-board { display: flex; flex-direction: column; gap: 1.5rem; }
        .notes-form { display: flex; flex-direction: column; gap: 0.75rem; align-items: flex-end; margin-bottom: 1.5rem; }
        .notes-date-heading { margin: 0; font-size: 0.875rem; font-weight: 600; color: var(--gray-500); }
        .notes-list { display: flex; flex-direction: column; gap: 0.75rem; margin: 0; padding: 0; list-style: none; }
        .notes-item { display: flex; flex-direction: column; gap: 0.25rem; padding: 0.75rem 1rem; border: 1px solid var(--gray-200); border-radius: 0.5rem; background: #fff; }
        .notes-item-meta { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.75rem; color: var(--gray-500); }
        .notes-item-body { font-size: 0.875rem; white-space: pre-wrap; }
        .notes-empty { padding: 1rem 0; font-size: 0.875rem; color: var(--gray-500); }
    </style>

    <form wire:submit="postMessage" class="notes-form">
        {{ $this->form }}

        <x-filament::button type="submit">
            {{ __('Post') }}
        </x-filament::button>
    </form>

    <div class="notes-board">
        @forelse ($grouped as $date => $notesForDate)
            <div>
                <h3 class="notes-date-heading">
                    {{ \Illuminate\Support\Carbon::parse($date)->isToday() ? __('Today') : \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, j F Y') }}
                </h3>

                <ul class="notes-list">
                    @foreach ($notesForDate as $note)
                        <li class="notes-item">
                            <div class="notes-item-meta">
                                <span>{{ $note->author?->name ?? __('Unknown') }}</span>
                                <span>{{ $note->created_at->format('H:i') }}</span>
                            </div>

                            <div class="notes-item-body">{{ $note->body }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="notes-empty">{{ __('No messages yet.') }}</p>
        @endforelse
    </div>
</x-filament-panels::page>
