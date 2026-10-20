<?php

declare(strict_types=1);

namespace Modules\Notes\Observers;

use Modules\Notes\Models\Note;

class NoteObserver
{
    /**
     * Handle the Note "creating" event.
     */
    public function creating(Note $note): void
    {
        if ($note->user_id !== null) {
            return;
        }

        $causerId = auth()->id();

        if (is_int($causerId) && $causerId > 0) {
            $note->user_id = $causerId;
        }
    }
}
