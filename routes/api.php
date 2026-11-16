<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BackorderController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PicklistController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.key')
    ->prefix('v1')
    ->group(function () {
        Route::apiResource('products', ProductController::class)->only(['index', 'show']);
        Route::apiResource('clients', ClientController::class)->only(['index', 'show']);
        Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'store']);
        Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['index', 'show']);
        Route::apiResource('picklists', PicklistController::class)->only(['index', 'show']);
        Route::apiResource('backorders', BackorderController::class)->only(['index', 'show']);
    });
