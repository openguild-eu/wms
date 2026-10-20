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
        Schema::create('picklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onUpdate('cascade')->onDelete('cascade');
            $table->boolean('completed')->default(false);
            $table->boolean('back_order')->default(false);
            $table->text('comments')->nullable();
            $table->integer('generated_year_picklist_id');
            $table->index('generated_year_picklist_id');
            $table->string('generated_custom_picklist_id')->nullable();
            $table->index('generated_custom_picklist_id');
            $table->timestamps();

            $table->unique(['warehouse_id', 'generated_custom_picklist_id']);
        });

        Schema::create('picklists_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('picklist_id')->constrained('picklists')->onDelete('cascade');
            $table->boolean('show_for_supplier')->default(false);
            $table->string('barcode');
            $table->string('reference_code')->nullable();
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->string('product_title');
            $table->boolean('scanned')->default(false);
            $table->timestamps();
        });

        Schema::create('picklist_failed_products', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('picklist_id')->constrained('picklists')->onDelete('cascade');
            $table->string('barcode')->nullable();
            $table->string('reference_code')->nullable();
            $table->bigInteger('total_quantity_scanned')->default(0);
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->string('product_title')->nullable();
            $table->timestamps();
        });

    }
};
