<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Warehouse;
use Illuminate\Http\Request;

abstract class Controller extends BaseController
{
    /**
     * Resolve the warehouse attached to the request by the `api.key` middleware.
     */
    protected function resolveWarehouse(Request $request): Warehouse
    {
        $warehouse = $request->attributes->get('warehouse');

        if (! $warehouse instanceof Warehouse) {
            abort(401, 'Unauthorized.');
        }

        return $warehouse;
    }
}
