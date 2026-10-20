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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->boolean('active')->default(false);
            $table->string('email');
            $table->string('vat_number')->nullable();
            $table->string('coc_number')->nullable();
            $table->string('debtor_number')->nullable();
            $table->string('iban_number')->nullable();
            $table->text('comments')->nullable();
            $table->string('company')->nullable();
            $table->integer('delivery_client_address_id')->unsigned()->nullable();
            $table->integer('bill_client_address_id')->unsigned()->nullable();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['warehouse_id', 'email']);
            $table->unique(['warehouse_id', 'vat_number']);
            $table->unique(['warehouse_id', 'coc_number']);
            $table->unique(['warehouse_id', 'debtor_number']);
            $table->unique(['warehouse_id', 'iban_number']);
        });

        Schema::create('client_addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('client_id')->constrained('clients')->onUpdate('cascade')->onDelete('cascade');
            $table->string('company')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->string('initials')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('zipcode')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->string('telephone_number')->nullable();
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreign('delivery_client_address_id')->references('id')->on('client_addresses')->onDelete('set null');
            $table->foreign('bill_client_address_id')->references('id')->on('client_addresses')->onDelete('set null');
        });

    }
};
