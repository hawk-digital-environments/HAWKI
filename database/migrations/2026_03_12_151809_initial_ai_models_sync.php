<?php

use App\Services\AI\Db\AiModelSyncService;
use App\Services\AI\Db\ToolSyncService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        app(AiModelSyncService::class)->sync();
        app(ToolSyncService::class)->syncFunctionTools();
    }

    public function down(): void
    {

    }
};
