<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Modules\Notes\Models\Note;

class Notes extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.notes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Notes';

    protected static ?string $title = 'Notes';

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
            ->components([
                Form::make([
                    Textarea::make('body')
                        ->label(__('Message'))
                        ->required()
                        ->rows(2),
                ])
                    ->livewireSubmitHandler('postMessage')
                    ->footer([
                        Actions::make([
                            Action::make('post')
                                ->label(__('Post'))
                                ->submit('postMessage'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function postMessage(): void
    {
        $data = $this->form->getState();

        Note::query()->create([
            'warehouse_id' => $this->getWarehouse()->getKey(),
            'body' => $data['body'],
        ]);

        $this->form->fill();

        Notification::make()
            ->title(__('Message posted'))
            ->success()
            ->send();
    }

    public function getWarehouse(): Warehouse
    {
        /** @var Warehouse $tenant */
        $tenant = Filament::getTenant();

        return $tenant;
    }

    /**
     * @return Collection<int, Note>
     */
    public function getMessages(): Collection
    {
        return Note::query()->with('author')->latest()->get();
    }
}
