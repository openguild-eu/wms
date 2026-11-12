<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backorders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('order_product_id')->constrained('order_products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('intended_order_statuses_id')->constrained('order_statuses')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_fulfilled')->default(0);
            $table->string('status')->default('open');
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['warehouse_id', 'status']);
        });
    }
};
