<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tools', static function (Blueprint $table): void {
            $table->string('access_rule', 80)->default('unavailable')->index();
        });
        // Intentionally grant no existing role new access. Administrators explicitly publish rules and grants.
        foreach (['tools.use', 'ai.capabilities.web_search.use', 'ai.capabilities.image_generation.use', 'tools.internal_search.use'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    public function down(): void
    {
        Schema::table('ai_tools', static fn (Blueprint $table) => $table->dropColumn('access_rule'));
    }
};
