<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('warehouses')->select('id')->orderBy('id')->each(function (object $warehouse) use ($now): void {
            $exists = DB::table('order_statuses')
                ->where('warehouse_id', $warehouse->id)
                ->where('name', 'Backordered')
                ->exists();

            if ($exists) {
                return;
            }

            DB::table('order_statuses')->insert([
                'warehouse_id' => $warehouse->id,
                'name' => 'Backordered',
                'color' => '#F59E0B',
                'generate_picklist' => false,
                'reserve_stock' => false,
                'reduce_stock' => false,
                'concepted' => false,
                'completed' => false,
                'on_hold' => true,
                'delivered' => false,
                'cancelled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }
};
