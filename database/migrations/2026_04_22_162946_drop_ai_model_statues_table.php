<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::drop('ai_model_statuses');
    }

    public function down(): void
    {
        $migration = include __DIR__ . '/2025_09_02_140101_create_ai_model_statuses_table.php';
        $migration->up();
    }
};
