<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `PasskeyService::backupPassKey()` called `PasskeyBackup::updateOrCreate()` with only one
 * argument, which makes Eloquent treat the whole payload as the match condition — so every
 * backup write inserted another row instead of updating the existing one. Installations
 * therefore have an unknown number of duplicates per username.
 *
 * The next migration puts a unique index on `passkey_backups.username`, which would fail on
 * those duplicates. Deleting them outright is not an option: a duplicate row is an encrypted
 * passkey backup, and if we picked the wrong survivor the user would lose their recovery path
 * with no way to get it back. So the losing rows are moved to `passkey_backup_archives`, where
 * an operator can still recover them until `expires_at` passes and
 * `passkey-backups:cleanup-archive` removes them.
 *
 * Survivor rule: the highest `id` per username. It is the latest inserted row and therefore the
 * best available approximation of the backup the user most recently submitted.
 */
return new class extends Migration {
    /** How long an archived duplicate stays recoverable. */
    private const int RETENTION_DAYS = 90;

    public function up(): void
    {
        Schema::create('passkey_backup_archives', static function (Blueprint $table) {
            $table->id();
            // The `passkey_backups.id` the row had before it was archived.
            $table->unsignedBigInteger('old_id');
            $table->string('username')->index();
            $table->text('ciphertext');
            $table->string('iv');
            $table->string('tag');
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('original_updated_at')->nullable();
            $table->timestamp('archived_at');
            $table->timestamp('expires_at')->index();
        });

        if (!Schema::hasTable('passkey_backups')) {
            return;
        }

        $archivedAt = now();
        $expiresAt = $archivedAt->copy()->addDays(self::RETENTION_DAYS);

        DB::table('passkey_backups')
            ->whereNotIn('id', static fn($query) => $query
                ->selectRaw('MAX(id)')->from('passkey_backups')->groupBy('username'))
            ->orderBy('id')
            ->chunkById(500, static function (Collection $rows) use ($archivedAt, $expiresAt) {
                DB::table('passkey_backup_archives')->insert(
                    $rows->map(static fn($row) => [
                        'old_id' => $row->id,
                        'username' => $row->username,
                        'ciphertext' => $row->ciphertext,
                        'iv' => $row->iv,
                        'tag' => $row->tag,
                        'original_created_at' => $row->created_at,
                        'original_updated_at' => $row->updated_at,
                        'archived_at' => $archivedAt,
                        'expires_at' => $expiresAt,
                    ])->all()
                );

                DB::table('passkey_backups')->whereIn('id', $rows->pluck('id')->all())->delete();
            });
    }

    /**
     * Moves the archived rows back into `passkey_backups` before dropping the archive, so
     * rolling back restores duplicates still retained in the archive. Rows deleted by
     * passkey-backups:cleanup-archive after the 90-day retention period cannot be restored.
     */
    public function down(): void
    {
        if (Schema::hasTable('passkey_backup_archives') && Schema::hasTable('passkey_backups')) {
            DB::table('passkey_backup_archives')
                ->orderBy('id')
                ->chunkById(500, static function (Collection $rows) {
                    DB::table('passkey_backups')->insert(
                        $rows->map(static fn($row) => [
                            'id' => $row->old_id,
                            'username' => $row->username,
                            'ciphertext' => $row->ciphertext,
                            'iv' => $row->iv,
                            'tag' => $row->tag,
                            'created_at' => $row->original_created_at,
                            'updated_at' => $row->original_updated_at,
                        ])->all()
                    );
                });
        }

        Schema::dropIfExists('passkey_backup_archives');
    }
};
