<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_providers', fn(Blueprint $table) => $table->text('additional_config')->nullable());
    }

    public function down(): void
    {
        Schema::table('ai_providers', fn(Blueprint $table) => $table->dropColumn('additional_config'));
    }
};
