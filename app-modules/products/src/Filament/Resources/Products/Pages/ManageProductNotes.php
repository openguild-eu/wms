<?php

declare(strict_types=1);

namespace Modules\Products\Filament\Resources\Products\Pages;

use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Modules\Notes\Filament\Pages\Concerns\HasNotesFeed;
use Modules\Products\Filament\Resources\Products\ProductResource;

class ManageProductNotes extends Page implements HasForms
{
    use HasNotesFeed;

    protected static string $resource = ProductResource::class;

    protected string $view = 'filament.pages.record-notes';

    protected static ?string $navigationLabel = 'Notes';

    protected static ?string $title = 'Notes';
}
