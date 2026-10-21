<?php

declare(strict_types=1);

namespace Modules\Notes\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Notes\Models\Note;

trait HasNotes
{
    /**
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'subject');
    }
}
