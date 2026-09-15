<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Descriptions belong to their model: deleting a model in Administration must not leave them behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_model_descriptions')->whereNotIn('ai_model_id', DB::table('ai_models')->select('id'))->delete();
        Schema::table('ai_model_descriptions', function (Blueprint $table) {
            $table->foreign('ai_model_id')->references('id')->on('ai_models')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_model_descriptions', fn(Blueprint $table) => $table->dropForeign(['ai_model_id']));
    }
};
