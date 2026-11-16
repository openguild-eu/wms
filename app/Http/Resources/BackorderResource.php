<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Backorders\Models\Backorder;

/** @mixin Backorder */
class BackorderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'order_id' => $this->order_id,
            'order_product_id' => $this->order_product_id,
            'product_id' => $this->product_id,
            'quantity_ordered' => $this->quantity_ordered,
            'quantity_fulfilled' => $this->quantity_fulfilled,
            'quantity_remaining' => $this->quantity_remaining,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'resolved_at' => $this->resolved_at,
        ];
    }
}
