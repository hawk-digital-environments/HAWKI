<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        // Fresh installs get the column straight from the base
        // create_assistants migration; this guarded migration delivers it
        // to databases that already ran the base migration before the
        // user-error pipeline existed.
        if (Schema::hasTable('assistant_attachments') && !Schema::hasColumn('assistant_attachments', 'rag_user_error')) {
            Schema::table('assistant_attachments', function (Blueprint $table): void {
                // User-facing failure key (`rag.ingestion.*`) shown by the
                // frontend; the technical `rag_error` stays for debugging.
                $table->string('rag_user_error')->nullable()->after('rag_error');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('assistant_attachments') && Schema::hasColumn('assistant_attachments', 'rag_user_error')) {
            Schema::table('assistant_attachments', function (Blueprint $table): void {
                $table->dropColumn('rag_user_error');
            });
        }
    }
};
