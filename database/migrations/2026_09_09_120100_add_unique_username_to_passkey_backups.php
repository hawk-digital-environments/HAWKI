<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One passkey backup per user, enforced by the database.
 *
 * Everything that writes a backup upserts on `username`, and the read path assumes a single
 * row. Without the unique index a concurrent double-submit (e.g. a retried registration
 * request) can still create a second row, and the `firstOrFail()` read path would then return
 * an arbitrary one of the two.
 *
 * Requires the preceding archive migration to have removed all existing duplicates.
 */
return new class extends Migration {
    private const string INDEX_NAME = 'passkey_backups_username_unique';

    public function up(): void
    {
        if (Schema::hasIndex('passkey_backups', self::INDEX_NAME)) {
            return;
        }

        Schema::table('passkey_backups', static function (Blueprint $table) {
            $table->unique('username', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (!Schema::hasIndex('passkey_backups', self::INDEX_NAME)) {
            return;
        }

        Schema::table('passkey_backups', static function (Blueprint $table) {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
