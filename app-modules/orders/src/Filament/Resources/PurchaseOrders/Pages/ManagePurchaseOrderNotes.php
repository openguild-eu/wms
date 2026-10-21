<?php

declare(strict_types=1);

namespace Modules\Orders\Filament\Resources\PurchaseOrders\Pages;

use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Modules\Notes\Filament\Pages\Concerns\HasNotesFeed;
use Modules\Orders\Filament\Resources\PurchaseOrders\PurchaseOrderResource;

class ManagePurchaseOrderNotes extends Page implements HasForms
{
    use HasNotesFeed;

    protected static string $resource = PurchaseOrderResource::class;

    protected string $view = 'filament.pages.record-notes';

    protected static ?string $navigationLabel = 'Notes';

    protected static ?string $title = 'Notes';
}
