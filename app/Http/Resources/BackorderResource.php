<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Backorders\Models\Backorder;

/**
 * No REST API controller exposes backorders yet, so there is no existing
 * endpoint to mirror - this class exists purely so webhook payloads follow
 * the same "explicit array of attributes" resource convention as
 * OrderResource/PurchaseOrderResource (see CLAUDE.md HTTP resource rules).
 *
 * @mixin Backorder
 */
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
            'intended_order_statuses_id' => $this->intended_order_statuses_id,
            'quantity_ordered' => $this->quantity_ordered,
            'quantity_fulfilled' => $this->quantity_fulfilled,
            'quantity_remaining' => $this->quantity_remaining,
            'status' => $this->status,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
