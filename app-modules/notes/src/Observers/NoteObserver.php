<?php

declare(strict_types=1);

namespace Modules\Notes\Observers;

use App\Models\Warehouse;
use Filament\Facades\Filament;
use Modules\Notes\Models\Note;

class NoteObserver
{
    /**
     * Handle the Note "creating" event.
     */
    public function creating(Note $note): void
    {
        $causerId = auth()->id();

        if ($note->user_id === null && is_int($causerId) && $causerId > 0) {
            $note->user_id = $causerId;
        }

        if ($note->warehouse_id === null) {
            $tenant = Filament::getTenant();

            if ($tenant instanceof Warehouse) {
                $note->warehouse_id = $tenant->getKey();
            }
        }
    }
}
