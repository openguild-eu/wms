<?php

declare(strict_types=1);

namespace Modules\Notes\Models;

use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Notes\Database\Factories\NoteFactory;
use Modules\Users\Models\User;

class Note extends Model
{
    use BelongsToWarehouse;

    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_id',
        'body',
    ];

    /**
     * @return array{filterableAttributes: list<string>, sortableAttributes: list<string>, searchableAttributes: list<string>}
     */
    public static function getSearchableSettings(): array
    {
        return [
            'filterableAttributes' => [
                'warehouse_id',
                'user_id',
            ],
            'sortableAttributes' => [
                'created_at',
                'updated_at',
            ],
            'searchableAttributes' => [
                'body',
            ],
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'user_id' => $this->user_id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
