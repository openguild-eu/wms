<?php

declare(strict_types=1);

namespace Modules\Clients\Filament\Resources\Clients\Pages;

use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Modules\Clients\Filament\Resources\Clients\ClientResource;
use Modules\Notes\Filament\Pages\Concerns\HasNotesFeed;

class ManageClientNotes extends Page implements HasForms
{
    use HasNotesFeed;

    protected static string $resource = ClientResource::class;

    protected string $view = 'filament.pages.record-notes';

    protected static ?string $navigationLabel = 'Notes';

    protected static ?string $title = 'Notes';
}
