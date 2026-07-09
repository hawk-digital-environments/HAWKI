<?php

use App\Services\Ai\ConfigFileSync\ConfigSyncMigrationTrait;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    use ConfigSyncMigrationTrait;

    public function down(): void
    {
    }
};
