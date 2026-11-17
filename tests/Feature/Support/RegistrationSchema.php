<?php
declare(strict_types=1);

namespace Tests\Feature\Support;

use Illuminate\Support\Facades\DB;

trait RegistrationSchema
{
    private function createRegistrationSchema(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('session.driver', 'array');
        DB::purge('sqlite');
        require_once database_path('migrations/2025_03_17_124719_add_is_removed_to_users_table.php');
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_09_09_120300_add_registration_fingerprint_to_users.php',
            '2025_01_16_121103_create_passkey_backups.php',
            '2025_08_21_175642_create_announcements.php',
            '2025_08_21_175841_create_announcement_user.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
        (new \AddIsRemovedToUsersTable())->up();
    }
}
