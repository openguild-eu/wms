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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->boolean('active')->default(false);
            $table->string('reference_code');
            $table->decimal('price', 12, 4)->nullable();
            $table->string('product_code');
            $table->boolean('stock_unlimited')->default(false);
            $table->string('barcode');
            $table->string('name');
            $table->string('weight')->nullable();
            $table->string('height')->nullable();
            $table->string('length')->nullable();
            $table->string('hs_code')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->text('description');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->onUpdate('cascade')->onDelete('set null');

            $table->timestamps();

            $table->unique(['warehouse_id', 'product_code']);
            $table->unique(['warehouse_id', 'barcode']);
            $table->unique(['warehouse_id', 'name']);
        });
    }
};
