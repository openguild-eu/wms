<?php

declare(strict_types=1);

namespace Modules\Notes\Filament\Pages\Concerns;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Modules\Notes\Models\Note;

/**
 * Shared by every per-record "Notes" tab (`ManageOrderNotes` and siblings):
 * the same Facebook-message-style feed as the central `/notes` page (see
 * `ListNotes`), scoped to one record's own notes instead of every note.
 *
 * @property-read Schema $form
 */
trait HasNotesFeed
{
    use InteractsWithForms;
    use InteractsWithRecord;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(Note::class)
            ->components([
                Textarea::make('body')
                    ->label(__('Message'))
                    ->required()
                    ->rows(2),
            ])
            ->statePath('data');
    }

    public function postMessage(): void
    {
        $record = $this->getRecord();
        $data = $this->form->getState();

        Note::query()->create([
            'subject_type' => $record::class,
            'subject_id' => $record->getKey(),
            'body' => $data['body'],
        ]);

        $this->form->fill();

        Notification::make()
            ->title(__('Message posted'))
            ->success()
            ->send();
    }

    /**
     * Named to avoid colliding with Livewire's own internal
     * `HandlesValidation::getMessages()` (a fallback source for validation
     * messages) — defining a method called `getMessages()` here would shadow
     * it and feed this Eloquent collection to the validator instead.
     *
     * @return Collection<int, Note>
     */
    public function getBoardMessages(): Collection
    {
        $record = $this->getRecord();

        return Note::query()
            ->where('subject_type', $record::class)
            ->where('subject_id', $record->getKey())
            ->with('author')
            ->latest()
            ->get();
    }
}
