<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\BackorderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Backorders\Models\Backorder;

class BackorderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $warehouse = $this->resolveWarehouse($request);

        $backorders = Backorder::query()
            ->where('warehouse_id', $warehouse->id)
            ->paginate(50);

        return BackorderResource::collection($backorders);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): BackorderResource
    {
        $warehouse = $this->resolveWarehouse($request);

        $backorder = Backorder::query()
            ->where('warehouse_id', $warehouse->id)
            ->findOrFail($id);

        return new BackorderResource($backorder);
    }
}
