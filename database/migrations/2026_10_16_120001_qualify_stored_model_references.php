<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts all stored model references to the provider-qualified identifier `<provider_id>/<model_id>`
 * (see {@see \App\Services\Ai\Models\Identifiers\Values\AiModelIdentifier}), so it is the only format inside HAWKI.
 *
 * This runs before the config sync can create the same `model_id` for a second provider, so every plain
 * `model_id` still belongs to exactly one model. Should a `model_id` already exist for several providers,
 * the model that was created first is used, because only that one existed when the reference was stored.
 * References to models that no longer exist are left untouched.
 *
 * `system_models` references its model by `ai_model_id`, so its plain `model_id` column is dropped.
 */
return new class extends Migration {
    /**
     * The columns that store a model reference.
     */
    private const array COLUMNS = [
        'ai_conv_msgs' => 'model',
        'messages' => 'model',
        'usage_records' => 'model',
    ];

    private const int CHUNK_SIZE = 100;

    public function up(): void
    {
        $map = [];
        foreach ($this->getModels() as $model) {
            $map[$model->model_id] ??= $model->provider_id . '/' . $model->model_id;
        }

        // A model id that is also the qualified identifier of another model (e.g. OpenRouter's `google/gemma-3`)
        // can not be told apart from an already converted reference, so it is left as it is
        $map = array_diff_key($map, array_flip($map));

        $this->replaceValues($map);

        Schema::table('system_models', function (Blueprint $table) {
            // MySQL keeps the index of the foreign key that was dropped before
            if (Schema::hasIndex('system_models', 'system_models_model_id_foreign')) {
                $table->dropIndex('system_models_model_id_foreign');
            }
            $table->dropColumn('model_id');
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
                'Cannot convert the model references back to plain model ids, these model ids exist for multiple providers: %s. Remove the duplicates first.',
                $duplicates->implode(', ')
            ));
        }

        $map = [];
        foreach ($this->getModels() as $model) {
            $map[$model->provider_id . '/' . $model->model_id] = $model->model_id;
        }
        $this->replaceValues($map);

        Schema::table('system_models', function (Blueprint $table) {
            $table->string('model_id')->nullable()->after('usage_type');
        });
        DB::table('system_models')->update([
            'model_id' => DB::raw('(SELECT ai_models.model_id FROM ai_models WHERE ai_models.id = system_models.ai_model_id)'),
        ]);
    }

    /**
     * @return iterable<object{model_id: string, provider_id: string}> Ordered by creation, oldest first.
     */
    private function getModels(): iterable
    {
        return DB::table('ai_models')
            ->join('ai_providers', 'ai_providers.id', '=', 'ai_models.provider_id')
            ->orderBy('ai_models.id')
            ->select('ai_models.model_id', 'ai_providers.provider_id')
            ->get();
    }

    /**
     * Replaces every value that is a key of $map with the mapped value, using one statement per chunk,
     * so large message tables are only scanned a few times.
     *
     * @param array<string, string> $map
     */
    private function replaceValues(array $map): void
    {
        if (empty($map)) {
            return;
        }

        $grammar = DB::connection()->getQueryGrammar();
        foreach (self::COLUMNS as $table => $column) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $wrappedTable = $grammar->wrapTable($table);
            $wrappedColumn = $grammar->wrap($column);
            foreach (array_chunk($map, self::CHUNK_SIZE, true) as $chunk) {
                $cases = [];
                $bindings = [];
                foreach ($chunk as $from => $to) {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = (string)$from;
                    $bindings[] = $to;
                }
                foreach ($chunk as $from => $to) {
                    $bindings[] = (string)$from;
                }

                DB::update(
                    sprintf(
                        'UPDATE %1$s SET %2$s = CASE %2$s %3$s END WHERE %2$s IN (%4$s)',
                        $wrappedTable,
                        $wrappedColumn,
                        implode(' ', $cases),
                        implode(', ', array_fill(0, count($chunk), '?'))
                    ),
                    $bindings
                );
            }
        }
    }
};
