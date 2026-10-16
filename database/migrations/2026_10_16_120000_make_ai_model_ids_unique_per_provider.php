<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allows the same `model_id` to be offered by several providers (e.g. `gemma-4-31b-it` on GWDG and Google).
 *
 * `ai_models.model_id` becomes unique per provider instead of globally. `system_models` can therefore no
 * longer reference a model by `model_id`, so it gets an `ai_model_id` foreign key to the numeric id.
 * `system_models.model_id` is kept (and still filled) for backwards compatibility until the next major release.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_models', function (Blueprint $table) {
            $table->foreignId('ai_model_id')->nullable()->after('usage_type')
                ->constrained('ai_models')->restrictOnDelete();
        });

        // model_id is still globally unique at this point, so the match is exact
        DB::table('system_models')->update([
            'ai_model_id' => DB::raw('(SELECT ai_models.id FROM ai_models WHERE ai_models.model_id = system_models.model_id)'),
        ]);

        Schema::table('system_models', function (Blueprint $table) {
            $table->dropForeign(['model_id']);
        });

        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropUnique(['model_id']);
            $table->index('model_id');
            $table->unique(['provider_id', 'model_id']);
        });
    }

    public function down(): void
    {
        $duplicates = DB::table('ai_models')
            ->select('model_id')
            ->groupBy('model_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('model_id');
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot make ai_models.model_id globally unique again, these model ids exist for multiple providers: %s. Remove the duplicates first.',
                $duplicates->implode(', ')
            ));
        }

        Schema::table('ai_models', function (Blueprint $table) {
            // MySQL uses the composite unique for the provider_id foreign key, so it needs its own index first
            $table->index('provider_id');
            $table->dropUnique(['provider_id', 'model_id']);
            $table->dropIndex(['model_id']);
            $table->unique('model_id');
        });

        Schema::table('system_models', function (Blueprint $table) {
            $table->foreign('model_id')->references('model_id')->on('ai_models')->restrictOnDelete();
            $table->dropConstrainedForeignId('ai_model_id');
        });
    }
};
