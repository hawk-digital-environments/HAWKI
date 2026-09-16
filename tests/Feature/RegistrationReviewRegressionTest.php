<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Announcements\AnnouncementService;
use App\Services\Announcements\Listeners\CheckRegistrationPolicyHealth;
use App\Services\Announcements\RegistrationPolicyService;
use App\Services\Auth\Exception\RegistrationAlreadyCompletedException;
use App\Services\Users\Repositories\UserRepository;
use App\Services\System\Health\Events\HealthCheckEvent;
use App\Services\System\Health\HealthChecker;
use App\Services\System\Health\HealthTimer;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Services\System\Health\Value\HealthCheckResult;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use Psr\Log\NullLogger;
use Tests\Feature\Support\RegistrationSchema;
use Tests\TestCase;

#[CoversNothing]
class RegistrationReviewRegressionTest extends TestCase
{
    use RegistrationSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRegistrationSchema();
    }

    public function testLegacyRetryReusesAnAccountWithoutAPublicKey(): void
    {
        $user = $this->user();
        $result = $this->app->make(UserRepository::class)->completeLegacyRegistration(
            'alice', 'Alice Retry', 'alice@example.test', 'employee'
        );
        self::assertSame($user->id, $result->id);
        self::assertSame('', $result->publicKey);
        self::assertSame('Alice Retry', $result->name);
        self::assertSame(1, DB::table('users')->count());
    }

    public function testLegacyRetryRejectsAnInitializedAccount(): void
    {
        $this->user()->update(['publicKey' => 'existing-key']);
        $this->expectException(RegistrationAlreadyCompletedException::class);
        $this->app->make(UserRepository::class)->completeLegacyRegistration(
            'alice', 'Alice Retry', 'alice@example.test', 'employee'
        );
    }

    public function testInAppPolicyAcceptanceRecordsValidConsentAndKeepsFirstSeenTime(): void
    {
        (require database_path('migrations/2026_09_09_120200_add_policy_consent_to_announcement_user.php'))->up();
        $user = $this->user();
        $announcement = $this->app->make(AnnouncementService::class)->createAnnouncement('Policy', 'basic-guidelines', 'policy');
        $user->markAnnouncementAsSeen($announcement->id);
        $seen = DB::table('announcement_user')->value('seen_at');
        $user->markAnnouncementAsAccepted($announcement->id);
        $policies = $this->app->make(RegistrationPolicyService::class);
        $policy = $policies->resolve();
        self::assertTrue($policies->hasValidConsent($user, $policy));
        self::assertSame($policy->locale, DB::table('announcement_user')->value('locale'));
        self::assertSame($seen, DB::table('announcement_user')->value('seen_at'));
    }

    public function testNonPolicyAcceptanceStillOnlyRecordsAcceptance(): void
    {
        (require database_path('migrations/2026_09_09_120200_add_policy_consent_to_announcement_user.php'))->up();
        $announcement = $this->app->make(AnnouncementService::class)->createAnnouncement('News', 'first-upload', 'info');
        $this->user()->markAnnouncementAsAccepted($announcement->id);
        self::assertNotNull(DB::table('announcement_user')->value('accepted_at'));
        self::assertNull(DB::table('announcement_user')->value('content_hash'));
        self::assertNull(DB::table('announcement_user')->value('locale'));
    }

    public function testBackupRollbacksRestoreRetainedDuplicatesAndOriginalFields(): void
    {
        $rows = [
            ['id' => 7, 'username' => 'alice', 'ciphertext' => 'old', 'iv' => 'iv1', 'tag' => 'tag1', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-02 00:00:00'],
            ['id' => 9, 'username' => 'alice', 'ciphertext' => 'new', 'iv' => 'iv2', 'tag' => 'tag2', 'created_at' => '2026-02-01 00:00:00', 'updated_at' => '2026-02-02 00:00:00'],
        ];
        DB::table('passkey_backups')->insert($rows);
        $archive = require database_path('migrations/2026_09_09_120000_archive_duplicate_passkey_backups.php');
        $unique = require database_path('migrations/2026_09_09_120100_add_unique_username_to_passkey_backups.php');
        $archive->up();
        $unique->up();
        $unique->down();
        $archive->down();
        self::assertFalse(Schema::hasTable('passkey_backup_archives'));
        self::assertFalse(Schema::hasIndex('passkey_backups', 'passkey_backups_username_unique'));
        self::assertSame($rows, DB::table('passkey_backups')->orderBy('id')->get()->map(fn($row) => (array)$row)->all());
    }

    public function testConsentRollbackRemovesColumnsAndUniqueIndexWithoutLosingAcceptance(): void
    {
        $user = $this->user();
        $announcement = $this->app->make(AnnouncementService::class)->createAnnouncement('Policy', 'basic-guidelines', 'policy');
        $migration = require database_path('migrations/2026_09_09_120200_add_policy_consent_to_announcement_user.php');
        $migration->up();
        $user->markAnnouncementAsAccepted($announcement->id);
        $accepted = DB::table('announcement_user')->value('accepted_at');
        $migration->down();
        self::assertFalse(Schema::hasColumn('announcement_user', 'locale'));
        self::assertFalse(Schema::hasColumn('announcement_user', 'content_hash'));
        self::assertSame($accepted, DB::table('announcement_user')->value('accepted_at'));
        DB::table('announcement_user')->insert(['announcement_id' => $announcement->id, 'user_id' => $user->id]);
        self::assertSame(2, DB::table('announcement_user')->count());
    }

    public function testCleanupDeletesExpiredArchivesAndKeepsFreshRows(): void
    {
        (require database_path('migrations/2026_09_09_120000_archive_duplicate_passkey_backups.php'))->up();
        foreach ([-1, 1] as $days) {
            DB::table('passkey_backup_archives')->insert([
                'old_id' => $days + 2, 'username' => 'alice', 'ciphertext' => 'ciphertext', 'iv' => 'iv', 'tag' => 'tag',
                'archived_at' => now()->subDays(90), 'expires_at' => now()->addDays($days),
            ]);
        }
        self::assertSame(0, Artisan::call('passkey-backups:cleanup-archive'));
        self::assertSame([3], DB::table('passkey_backup_archives')->pluck('old_id')->all());
        self::assertStringContainsString('1 expired archived passkey backup(s) deleted, 1 remaining.', Artisan::output());
    }

    public function testMissingPolicyDegradesDeepHealthChecks(): void
    {
        $timer = Mockery::mock(HealthTimer::class);
        $timer->shouldNotReceive('markAsFailed');
        $timer->shouldReceive('markAsHealthy')->once();
        $checker = $this->policyHealthChecker($timer);
        self::assertSame('degraded', $checker->deepCheck()->getStatus());
    }

    public function testQuickHealthChecksDoNotReadRegistrationPolicy(): void
    {
        $timer = Mockery::mock(HealthTimer::class);
        $timer->shouldNotReceive('markAsFailed');
        DB::enableQueryLog();
        $checker = new HealthChecker(new NullLogger(), $this->app->make(Dispatcher::class), $timer);
        $result = $checker->quickCheck();
        self::assertSame([], DB::getQueryLog());
        self::assertSame('healthy', $result->getStatus());
    }

    public function testPolicyDatabaseFailureMakesDeepHealthChecksUnhealthy(): void
    {
        Schema::drop('announcement_user');
        Schema::drop('announcements');
        $timer = Mockery::mock(HealthTimer::class);
        $timer->shouldReceive('markAsFailed')->once();
        $checker = $this->policyHealthChecker($timer);
        self::assertSame('unhealthy', $checker->deepCheck()->getStatus());
    }

    private function policyHealthChecker(HealthTimer $timer): HealthChecker
    {
        Redis::shouldReceive('ping')->once();
        Cache::shouldReceive('put')->once();
        Cache::shouldReceive('get')->once()->andReturn('test_value');
        Cache::shouldReceive('forget')->once();
        $listener = $this->app->make(CheckRegistrationPolicyHealth::class);
        $events = new Dispatcher();
        $events->listen(HealthCheckEvent::class, $listener->handle(...));
        $events->listen(HealthCheckEvent::class, static function (HealthCheckEvent $event): void {
            $event->addResult(new HealthCheckResult('storage', HealthCheckResult::STATUS_OK, 'Test storage'));
        });
        return new HealthChecker(new NullLogger(), $events, $timer);
    }

    private function user(): User
    {
        return User::withoutEvents(fn(): User => User::query()->create([
            'username' => 'alice', 'name' => 'Alice', 'email' => 'alice@example.test',
            'employeetype' => 'employee', 'publicKey' => '', 'isRemoved' => false,
        ]));
    }
}
