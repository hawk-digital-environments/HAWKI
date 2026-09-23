<?php
declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;
use Tests\Feature\Support\RegistrationSchema;

#[CoversNothing]
class RegistrationMigrationsTest extends TestCase
{
    use RegistrationSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRegistrationSchema();
    }

    public function testBackupMigrationArchivesOlderDuplicatesAndEnforcesOneBackupPerUsername(): void
    {
        DB::table('passkey_backups')->insert([
            [
                'id' => 7, 'username' => 'alice', 'ciphertext' => 'old', 'iv' => 'old-iv', 'tag' => 'old-tag',
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-02 00:00:00',
            ],
            [
                'id' => 9, 'username' => 'alice', 'ciphertext' => 'new', 'iv' => 'new-iv', 'tag' => 'new-tag',
                'created_at' => '2026-02-01 00:00:00', 'updated_at' => '2026-02-02 00:00:00',
            ],
        ]);

        (require database_path('migrations/2026_09_09_120000_archive_duplicate_passkey_backups.php'))->up();
        (require database_path('migrations/2026_09_09_120100_add_unique_username_to_passkey_backups.php'))->up();

        self::assertSame([9], DB::table('passkey_backups')->pluck('id')->all());
        self::assertSame(7, DB::table('passkey_backup_archives')->value('old_id'));
        self::assertSame('2026-01-01 00:00:00', DB::table('passkey_backup_archives')->value('original_created_at'));

        $this->expectException(QueryException::class);
        DB::table('passkey_backups')->insert([
            'username' => 'alice', 'ciphertext' => 'third', 'iv' => 'iv', 'tag' => 'tag',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function testConsentMigrationKeepsHighestIdAndAddsUniquePair(): void
    {
        DB::table('users')->insert([
            'id' => 1, 'name' => 'Alice', 'email' => 'alice@example.test', 'username' => 'alice',
            'publicKey' => '', 'employeetype' => 'employee', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('announcements')->insert([
            'id' => 1, 'title' => 'Policy', 'view' => 'basic-guidelines', 'type' => 'policy',
            'is_forced' => false, 'is_global' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('announcement_user')->insert([
            [
                'id' => 4, 'announcement_id' => 1, 'user_id' => 1,
                'seen_at' => '2026-02-01 00:00:00', 'accepted_at' => '2026-02-02 00:00:00',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 6, 'announcement_id' => 1, 'user_id' => 1,
                'seen_at' => '2026-01-01 00:00:00', 'accepted_at' => null,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        (require database_path('migrations/2026_09_09_120200_add_policy_consent_to_announcement_user.php'))->up();

        $rows = DB::table('announcement_user')->get();
        self::assertCount(1, $rows);
        self::assertSame(6, $rows->first()->id);
        self::assertSame('2026-01-01 00:00:00', $rows->first()->seen_at);
        self::assertSame('2026-02-02 00:00:00', $rows->first()->accepted_at);

        $this->expectException(QueryException::class);
        DB::table('announcement_user')->insert([
            'announcement_id' => 1, 'user_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function testArchiveCleanupCommandSucceedsBeforeTheArchiveMigrationRuns(): void
    {
        self::assertSame(0, Artisan::call('passkey-backups:cleanup-archive'));
        self::assertStringContainsString('No passkey backup archive table present', Artisan::output());
    }
}
