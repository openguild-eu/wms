<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\PurchaseOrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Orders\Models\PurchaseOrder;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $warehouse = $this->resolveWarehouse($request);

        $purchaseOrders = PurchaseOrder::query()
            ->where('warehouse_id', $warehouse->id)
            ->paginate(50);

        return PurchaseOrderResource::collection($purchaseOrders);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): PurchaseOrderResource
    {
        $warehouse = $this->resolveWarehouse($request);

        $purchaseOrder = PurchaseOrder::query()
            ->where('warehouse_id', $warehouse->id)
            ->findOrFail($id);

        return new PurchaseOrderResource($purchaseOrder);
    }
}
