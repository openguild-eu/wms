<?php

declare(strict_types=1);

namespace Modules\Picklists\Filament\Resources\Picklists\Pages;

use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Modules\Notes\Filament\Pages\Concerns\HasNotesFeed;
use Modules\Picklists\Filament\Resources\Picklists\PicklistResource;

class ManagePicklistNotes extends Page implements HasForms
{
    use HasNotesFeed;

    protected static string $resource = PicklistResource::class;

    protected string $view = 'filament.pages.record-notes';

    protected static ?string $navigationLabel = 'Notes';

    protected static ?string $title = 'Notes';
}
