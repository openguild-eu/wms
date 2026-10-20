<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $connectionName = is_string($connection) ? $connection : null;

        if (Schema::connection($connectionName)->hasColumn(config('activitylog.table_name'), 'attribute_changes')) {
            return;
        }

        Schema::connection($connectionName)->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->json('attribute_changes')->nullable();
        });
    }
};
