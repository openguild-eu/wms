<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\PicklistResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Picklists\Models\Picklist;

class PicklistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $warehouse = $this->resolveWarehouse($request);

        $picklists = Picklist::query()
            ->where('warehouse_id', $warehouse->id)
            ->paginate(50);

        return PicklistResource::collection($picklists);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): PicklistResource
    {
        $warehouse = $this->resolveWarehouse($request);

        $picklist = Picklist::query()
            ->where('warehouse_id', $warehouse->id)
            ->findOrFail($id);

        return new PicklistResource($picklist);
    }
}
