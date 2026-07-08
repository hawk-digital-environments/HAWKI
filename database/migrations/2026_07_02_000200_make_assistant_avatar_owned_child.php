<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An avatar now belongs to exactly one assistant (1:1 owned child).
        Schema::table('assistant_avatars', function (Blueprint $table) {
            $table->foreignId('assistant_id')
                ->nullable()
                ->after('id')
                ->constrained('assistants')
                ->cascadeOnDelete();

            // Enforces the 1:1 relationship; NULLs (legacy orphan avatars) are
            // allowed since MySQL/SQLite permit multiple NULLs in a unique index.
            $table->unique('assistant_id');
        });

        // Drop the legacy shared-catalog link on the assistant.
        Schema::table('assistants', function (Blueprint $table) {
            $table->dropForeign(['avatar_id']);
            $table->dropColumn('avatar_id');
        });
    }

    public function down(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->foreignId('avatar_id')
                ->nullable()
                ->after('top_p')
                ->constrained('assistant_avatars')
                ->nullOnDelete();
        });

        Schema::table('assistant_avatars', function (Blueprint $table) {
            $table->dropForeign(['assistant_id']);
            $table->dropUnique(['assistant_id']);
            $table->dropColumn('assistant_id');
        });
    }
};
