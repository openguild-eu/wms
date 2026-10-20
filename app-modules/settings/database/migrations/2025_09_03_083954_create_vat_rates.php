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
        Schema::create('vat_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 12, 4);
            $table->foreignId('warehouse_id')->constrained('warehouses')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['warehouse_id', 'name']);
            $table->unique(['warehouse_id', 'rate']);
        });
    }
};
