<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The temporary users.locale column (added 2026-08-19 while the config engine was
 * missing) is replaced by the `locale` user setting. Before the column disappears,
 * every existing preference is moved into the sparse settings rows.
 */
return new class() extends Migration {
    public function up(): void
    {
        // Move each user's non-null locale preference into the settings rows before
        // the column is dropped, so nothing is lost.
        DB::table('users')
            ->whereNotNull('locale')
            ->orderBy('id')
            ->chunkById(500, static function (Collection $users): void {
                $rows = array_map(static fn(stdClass $user): array => [
                    'user_id' => $user->id,
                    'namespace' => 'hawki-core',
                    'key' => 'locale',
                    'value' => $user->locale,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $users->all());

                DB::table('user_setting_values')->upsert(
                    $rows,
                    ['user_id', 'namespace', 'key'],
                    ['value'],
                );
            });

        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->string('locale', 5)->nullable()->after('bio');
        });

        // Restore the moved locale preferences back into the column.
        DB::table('user_setting_values')
            ->where('namespace', 'hawki-core')
            ->where('key', 'locale')
            ->whereNotNull('value')
            ->orderBy('id')
            ->chunkById(500, static function (Collection $rows): void {
                foreach ($rows as $row) {
                    DB::table('users')
                        ->where('id', $row->user_id)
                        ->update(['locale' => $row->value]);
                }
            });
    }
};
