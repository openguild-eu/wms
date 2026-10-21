<?php

declare(strict_types=1);

namespace Modules\Notes\Filament\Resources\Notes\Pages;

use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Modules\Clients\Models\Client;
use Modules\Notes\Filament\Resources\Notes\NoteResource;
use Modules\Notes\Models\Note;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\PurchaseOrder;
use Modules\Picklists\Models\Picklist;
use Modules\Products\Models\Product;

/**
 * The central `/notes` feed: every note (general or linked to a subject),
 * newest first, grouped by day, with a message box at the top — one
 * continuous page, not a table. This deliberately does not extend
 * `ListRecords`: the user asked for a Facebook-message-style feed, not a
 * table overview with columns/filters.
 *
 * @property-read Schema $form
 */
class ListNotes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = NoteResource::class;

    protected string $view = 'filament.pages.notes';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(Note::class)
            ->components([
                MorphToSelect::make('subject')
                    ->label(__('Link to'))
                    ->types([
                        Type::make(Order::class)
                            ->getOptionLabelFromRecordUsing(fn (Order $record): string => $record->getActivityLogTitle())
                            ->searchColumns(['generated_custom_order_id', 'custom_order_id', 'invoice_name', 'delivery_name', 'email']),
                        Type::make(PurchaseOrder::class)
                            ->getOptionLabelFromRecordUsing(fn (PurchaseOrder $record): string => $record->getActivityLogTitle())
                            ->searchColumns(['generated_custom_purchase_order_id']),
                        Type::make(Product::class)
                            ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->getActivityLogTitle())
                            ->searchColumns(['name', 'reference_code', 'product_code', 'barcode']),
                        Type::make(Picklist::class)
                            ->getOptionLabelFromRecordUsing(fn (Picklist $record): string => $record->getActivityLogTitle())
                            ->searchColumns(['generated_custom_picklist_id']),
                        Type::make(Client::class)
                            ->getOptionLabelFromRecordUsing(fn (Client $record): string => $record->getActivityLogTitle())
                            ->searchColumns(['company', 'email']),
                    ]),
                Textarea::make('body')
                    ->label(__('Message'))
                    ->required()
                    ->rows(2),
            ])
            ->statePath('data');
    }

    public function postMessage(): void
    {
        $data = $this->form->getState();

        Note::query()->create([
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
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
        return Note::query()->with(['author', 'subject'])->latest()->get();
    }
}
