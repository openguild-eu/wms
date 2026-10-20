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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subdomain_id')->constrained('subdomains')->cascadeOnDelete();
            $table->string('name');
            $table->string('currency')->default('EUR');
            $table->timestamps();
        });

        Schema::create('user_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onUpdate('set null')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onUpdate('set null')->onDelete('cascade');
            $table->timestamps();
        });

    }
};
