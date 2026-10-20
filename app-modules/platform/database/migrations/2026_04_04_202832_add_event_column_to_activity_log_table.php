<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEventColumnToActivityLogTable extends Migration
{
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $tableName = config('activitylog.table_name');

        Schema::connection(is_string($connection) || $connection === null ? $connection : null)
            ->table((string) $tableName, function (Blueprint $table): void {
                $table->string('event')->nullable()->after('description');
            });
    }
}
