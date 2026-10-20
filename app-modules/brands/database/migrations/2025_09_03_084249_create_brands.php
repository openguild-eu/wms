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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->boolean('active')->default(false);
            $table->string('reference_code');
            $table->string('name');
            $table->text('description');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['warehouse_id', 'reference_code']);
            $table->unique(['warehouse_id', 'name']);
        });

    }
};
