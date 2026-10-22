<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the superseded `capabilities` attribute: per-assistant capability
 * variant selections ("capability:<key>:<native|auto|<tool>>"). Assistants
 * carry tools through the `ai_tools` relationship, and the knowledge tool is
 * granted ambiently by the RAG module whenever the assistant has attachments
 * (see RagKnowledgeAgentTool) — the stored selections had no remaining role.
 */
return new class() extends Migration {
    public function up(): void
    {
        Schema::table('assistants', static function (Blueprint $table): void {
            $table->dropColumn('capabilities');
        });
    }

    public function down(): void
    {
        Schema::table('assistants', static function (Blueprint $table): void {
            $table->json('capabilities')->nullable();
        });
    }
};
