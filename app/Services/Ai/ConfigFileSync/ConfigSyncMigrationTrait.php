<?php
declare(strict_types=1);

namespace App\Services\Ai\ConfigFileSync;

/** Historical migrations keep this trait; configuration imports are now explicit. */
trait ConfigSyncMigrationTrait
{
    public function up(): void
    {
        // Run ai:config:import after migrations to seed configuration on a fresh install.
    }
}
