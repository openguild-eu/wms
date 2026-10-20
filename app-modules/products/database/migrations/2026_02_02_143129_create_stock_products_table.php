<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->integer('on_stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('reserved_on_picklists')->default(0);
            $table->integer('free_on_stock_quantity')->default(0);
            $table->timestamps();

            $table->unique('product_id');
        });

    }
};
