<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Orders\Models\Order;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $warehouse = $this->resolveWarehouse($request);

        $orders = Order::query()
            ->where('warehouse_id', $warehouse->id)
            ->with(['products', 'client'])
            ->paginate(50);

        return OrderResource::collection($orders);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): OrderResource
    {
        $warehouse = $this->resolveWarehouse($request);

        $order = Order::query()
            ->where('warehouse_id', $warehouse->id)
            ->with(['products', 'client'])
            ->findOrFail($id);

        return new OrderResource($order);
    }

    /**
     * Store a newly created resource.
     */
    public function store(StoreOrderRequest $request, OrderCreationService $service): JsonResponse
    {
        $warehouse = $this->resolveWarehouse($request);

        $order = $service->create($warehouse, $request->validated());

        $order->load(['products', 'client']);

        return (new OrderResource($order))
            ->response()
            ->setStatusCode($order->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
