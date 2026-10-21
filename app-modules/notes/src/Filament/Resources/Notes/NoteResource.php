<?php

declare(strict_types=1);

namespace Modules\Notes\Filament\Resources\Notes;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Modules\Clients\Filament\Resources\Clients\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Notes\Filament\Resources\Notes\Pages\ListNotes;
use Modules\Notes\Models\Note;
use Modules\Orders\Filament\Resources\Orders\OrderResource;
use Modules\Orders\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\PurchaseOrder;
use Modules\Picklists\Filament\Resources\Picklists\PicklistResource;
use Modules\Picklists\Models\Picklist;
use Modules\Products\Filament\Resources\Products\ProductResource;
use Modules\Products\Models\Product;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Notes';

    protected static ?string $modelLabel = 'Note';

    public static function getSubjectUrl(Note $note): ?string
    {
        $subject = $note->subject;

        if (! $subject instanceof Model) {
            return null;
        }

        return match ($subject::class) {
            Order::class => OrderResource::getUrl('edit', ['record' => $subject]),
            PurchaseOrder::class => PurchaseOrderResource::getUrl('edit', ['record' => $subject]),
            Product::class => ProductResource::getUrl('edit', ['record' => $subject]),
            Picklist::class => PicklistResource::getUrl('view', ['record' => $subject]),
            Client::class => ClientResource::getUrl('edit', ['record' => $subject]),
            default => null,
        };
    }

    /**
     * The subject's own "Notes" tab (`Manage<X>Notes`) — every one of the
     * five subject resources registers a `'notes'` page (see §7 of
     * blueprints/notes.md), so this mirrors `getSubjectUrl()` exactly but
     * points there instead of at the subject's edit/view page.
     */
    public static function getSubjectNotesUrl(Note $note): ?string
    {
        $subject = $note->subject;

        if (! $subject instanceof Model) {
            return null;
        }

        return match ($subject::class) {
            Order::class => OrderResource::getUrl('notes', ['record' => $subject]),
            PurchaseOrder::class => PurchaseOrderResource::getUrl('notes', ['record' => $subject]),
            Product::class => ProductResource::getUrl('notes', ['record' => $subject]),
            Picklist::class => PicklistResource::getUrl('notes', ['record' => $subject]),
            Client::class => ClientResource::getUrl('notes', ['record' => $subject]),
            default => null,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotes::route('/'),
        ];
    }
}
