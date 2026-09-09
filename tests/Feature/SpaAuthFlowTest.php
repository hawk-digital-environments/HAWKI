<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithCredentialsInterface;
use App\Services\Auth\Exception\AuthFailedException;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
class SpaAuthFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([\App\JsonApi\V1\Auths\AuthSchema::class, \App\JsonApi\V1\Connections\ConnectionSchema::class] as $schema) {
            $this->app->afterResolving($schema, static fn($instance) => $instance->useServiceContainerFallback(true));
        }
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('session.driver', 'array');
        config()->set('cache.default', 'array');
        config()->set('app.maintenance.driver', 'file');
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2026_08_19_130000_add_locale_to_users_table',
            '2025_01_16_121103_create_passkey_backups',
            '2025_08_21_175642_create_announcements',
            '2025_08_21_175841_create_announcement_user',
            '2025_10_09_090741_create_user_keychain_values_table',
            '2026_06_07_175604_create_frontend_migrations_table',
            '2026_06_07_181418_create_applied_frontend_migrations_table',
            '2026_06_07_181544_create_frontend_migration_userdata_table',
            '2026_09_09_120000_archive_duplicate_passkey_backups',
            '2026_09_09_120100_add_unique_username_to_passkey_backups',
            '2026_09_09_120200_add_policy_consent_to_announcement_user',
            '2026_09_09_120300_add_registration_fingerprint_to_users',
        ] as $migration) {
            (require database_path('migrations/' . $migration . '.php'))->up();
        }
        \Illuminate\Support\Facades\Schema::table('users', static fn(\Illuminate\Database\Schema\Blueprint $table) => $table->boolean('isRemoved')->default(false));
        $this->app->instance(AuthServiceInterface::class, new class implements AuthServiceInterface, AuthServiceWithCredentialsInterface {
            private string $account = '';
            private string $password = '';
            public function useCredentials(string $username, string $password): void { $this->account = $username; $this->password = $password; }
            public function forgetCredentials(): void { $this->account = $this->password = ''; }
            public function authenticate(Request $request): AuthenticatedUserInfo {
                if ($this->password !== 'test-password') throw new AuthFailedException('Invalid credentials');
                return new AuthenticatedUserInfo($this->account, 'Test User', $this->account . '@example.test', 'employee');
            }
        });
    }

    private function headers(): array
    {
        return ['Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json', 'Origin' => 'http://localhost'];
    }

    public function testGuestCanDiscoverCredentialsAndConsumeAnErrorOnce(): void
    {
        $this->withSession(['auth.last_error' => 'provider_failed'])
            ->getJson('/api/hawki/v1/auth/hawki', $this->headers())
            ->assertOk()->assertJsonPath('data.attributes.mode', 'credentials')
            ->assertJsonPath('data.attributes.start_url', null)
            ->assertJsonPath('data.attributes.last_error', 'provider_failed');
        $this->getJson('/api/hawki/v1/auth/hawki', $this->headers())
            ->assertOk()->assertJsonPath('data.attributes.last_error', null);
    }

    public function testLegacyGatewayRoutesCanBeSwitchedToTheSpa(): void
    {
        config()->set('app.spa_auth', true);
        $this->get('/login')->assertRedirect('/new/auth/login');
        $this->startRegistration();
        $this->get('/register')->assertRedirect('/new/auth/register');
        $this->actingAs($this->user())->get('/handshake')->assertRedirect('/new/auth/handshake');
    }

    public function testBadCredentialsHaveAMachineCode(): void
    {
        $this->postJson('/api/hawki/v1/auth/actions/login', ['account' => 'alice', 'password' => 'wrong'], $this->headers())
            ->assertStatus(401)->assertJsonPath('errors.0.code', 'invalid_credentials');
    }

    public function testNewIdentityEntersRegistrationAndReturnsARegisteringConnection(): void
    {
        $this->postJson('/api/hawki/v1/auth/actions/login', ['account' => 'alice', 'password' => 'test-password'], $this->headers())
            ->assertOk()->assertJsonPath('meta.next', 'register')
            ->assertJsonPath('data.attributes.type', 'internal_registering_user')
            ->assertJsonPath('data.attributes.userinfo.username', 'alice');
        self::assertSame(0, DB::table('users')->count());
        self::assertSame('new', session()->get('auth.registration_ui'));
    }

    public function testSpaRegistrationCannotWriteThroughLegacyRegistrationEndpoints(): void
    {
        config()->set('app.spa_auth', false);
        $this->startRegistration();

        $this->post('/req/complete_registration')->assertForbidden();
        $this->post('/req/profile/backupPassKey', [
            'cipherText' => 'encrypted',
            'iv' => 'iv',
            'tag' => 'tag',
        ])->assertForbidden();

        self::assertSame(0, DB::table('users')->count());
        self::assertSame(0, DB::table('passkey_backups')->count());
    }

    public function testEnteringTheSpaMarksAnExistingLegacyRegistrationSession(): void
    {
        $this->withSession([
            'registration_access' => true,
            'authenticatedUserInfo' => json_encode([
                'username' => 'alice',
                'name' => 'Alice',
                'email' => 'alice@example.test',
                'employeetype' => 'employee',
            ]),
        ])->get('/new/chat')->assertOk();

        self::assertSame('new', session()->get('auth.registration_ui'));
        $this->post('/req/complete_registration')->assertForbidden();
        self::assertSame(0, DB::table('users')->count());
    }

    public function testSpaAuthFlagRejectsAnUnmarkedStaleRegistrationSession(): void
    {
        config()->set('app.spa_auth', true);
        $session = [
            'registration_access' => true,
            'authenticatedUserInfo' => json_encode([
                'username' => 'alice',
                'name' => 'Alice',
                'email' => 'alice@example.test',
                'employeetype' => 'employee',
            ]),
        ];

        $this->withSession($session)->post('/req/complete_registration')->assertForbidden();
        $this->withSession($session)->post('/req/profile/backupPassKey', [
            'cipherText' => 'encrypted',
            'iv' => 'iv',
            'tag' => 'tag',
        ])->assertForbidden();

        self::assertSame(0, DB::table('users')->count());
        self::assertSame(0, DB::table('passkey_backups')->count());
    }

    public function testLegacyRegistrationStillCompletesWhenSpaAuthIsDisabled(): void
    {
        config()->set('app.spa_auth', false);
        $this->post('/req/login', [
            'account' => 'alice',
            'password' => 'test-password',
        ])->assertOk()->assertJsonPath('redirectUri', '/register');

        self::assertFalse(session()->has('auth.registration_ui'));
        $this->post('/req/complete_registration')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirectUri', '/chat');

        self::assertSame(1, DB::table('users')->count());
        $this->assertAuthenticated();
    }

    public function testStaleLegacyRegistrationCannotResetAnExistingUser(): void
    {
        $user = $this->user();
        DB::table('users')->where('id', $user->id)->update(['publicKey' => 'existing-public-key']);

        $this->withSession([
            'registration_access' => true,
            'authenticatedUserInfo' => json_encode([
                'username' => 'alice',
                'name' => 'Changed Name',
                'email' => 'changed@example.test',
                'employeetype' => 'changed',
            ]),
        ])->post('/req/complete_registration')->assertStatus(409);

        $stored = DB::table('users')->where('id', $user->id)->first();
        self::assertSame('existing-public-key', $stored->publicKey);
        self::assertSame('Alice', $stored->name);
        self::assertFalse(session()->has('registration_access'));
    }

    public function testSessionWithoutKeysIsClassifiedAsSetup(): void
    {
        $user = $this->user();
        $this->actingAs($user)->getJson('/api/hawki/v1/connections/hawki', $this->headers())
            ->assertOk()->assertJsonPath('data.attributes.keychain_state', 'setup_required');
    }

    public function testMissingPolicyFailsClosed(): void
    {
        $this->getJson('/api/hawki/v1/announcements/actions/registration-policy', $this->headers())
            ->assertStatus(503)->assertJsonPath('errors.0.code', 'registration_policy_unavailable');
    }

    public function testBackupOnlyExposesMeAndReportsAnAbsentBackup(): void
    {
        $this->actingAs($this->user())
            ->getJson('/api/hawki/v1/passkey-backups/me', $this->headers())
            ->assertNotFound()->assertJsonPath('errors.0.code', 'passkey_backup_not_found');
        $other = $this->getJson('/api/hawki/v1/passkey-backups/another-user', $this->headers())->assertNotFound();
        self::assertNull($other->json('errors.0.code'));
        DB::table('passkey_backups')->insert(['username' => 'alice', 'ciphertext' => 'encrypted', 'iv' => 'iv', 'tag' => 'tag']);
        $this->getJson('/api/hawki/v1/passkey-backups/me', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.type', 'passkey-backups')
            ->assertJsonPath('data.id', 'me')
            ->assertJsonPath('data.attributes.ciphertext', 'encrypted')
            ->assertJsonPath('data.attributes.iv', 'iv')
            ->assertJsonPath('data.attributes.tag', 'tag');
    }

    public function testLogoutReturnsTheFrontendContractAndClearsAuthentication(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/hawki/v1/auth/actions/logout', [], $this->headers())
            ->assertOk();
        $this->assertGuest();
    }

    public function testRegistrationCommitsKeysConsentBackupAndMigrationsAndRetriesWithoutWrites(): void
    {
        $payload = $this->registrationPayload();
        DB::table('frontend_migrations')->insert(['migration_name' => 'test_migration', 'has_userdata' => false]);
        $this->startRegistration();
        $this->postJson('/api/hawki/v1/auth/actions/complete-registration', $payload, $this->headers())
            ->assertOk()->assertJsonPath('data.attributes.keychain_state', 'initialized');
        self::assertSame(1, DB::table('users')->count());
        self::assertSame(3, DB::table('user_keychain_values')->count());
        self::assertSame(1, DB::table('passkey_backups')->count());
        self::assertSame(1, DB::table('applied_frontend_migrations')->count());
        $consent = DB::table('announcement_user')->first();
        self::assertSame($payload['policy']['hash'], $consent->content_hash);
        self::assertNotNull($consent->accepted_at);
        self::assertFalse(session()->has('registration_access'));
        $this->assertAuthenticated();

        $before = DB::table('user_keychain_values')->orderBy('id')->get()->toJson();
        // Idempotent retries must still work after a policy becomes unavailable.
        DB::table('announcements')->update(['expires_at' => now()->subDay()]);
        $this->postJson('/api/hawki/v1/auth/actions/complete-registration', $payload, $this->headers())->assertOk();
        self::assertSame($before, DB::table('user_keychain_values')->orderBy('id')->get()->toJson());
        $payload['backup']['ciphertext'] = base64_encode('different encrypted backup');
        $this->postJson('/api/hawki/v1/auth/actions/complete-registration', $payload, $this->headers())
            ->assertStatus(409)->assertJsonPath('errors.0.code', 'registration_already_completed');
    }

    public function testPolicyChangeLeavesNoRegistrationWrites(): void
    {
        $payload = $this->registrationPayload();
        $payload['policy']['hash'] = str_repeat('0', 64);
        $this->startRegistration();
        $this->postJson('/api/hawki/v1/auth/actions/complete-registration', $payload, $this->headers())
            ->assertStatus(409)->assertJsonPath('errors.0.code', 'policy_changed');
        foreach (['users', 'user_keychain_values', 'passkey_backups', 'announcement_user'] as $table) {
            self::assertSame(0, DB::table($table)->count(), $table);
        }
        self::assertTrue(session()->get('registration_access'));
    }

    public function testLateBackupFailureRollsBackTheWholeRegistration(): void
    {
        $payload = $this->registrationPayload();
        $this->startRegistration();
        DB::unprepared("CREATE TRIGGER reject_test_backup BEFORE INSERT ON passkey_backups BEGIN SELECT RAISE(ABORT, 'test backup write failure'); END");
        $this->postJson('/api/hawki/v1/auth/actions/complete-registration', $payload, $this->headers())->assertStatus(500);
        foreach (['users', 'user_keychain_values', 'passkey_backups', 'announcement_user', 'applied_frontend_migrations'] as $table) {
            self::assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertGuest();
        self::assertTrue(session()->get('registration_access'));
    }

    public function testOnlyHashBoundConsentSkipsThePolicyForSetup(): void
    {
        $payload = $this->registrationPayload();
        $user = $this->user();
        $this->actingAs($user);
        DB::table('announcement_user')->insert([
            'announcement_id' => $payload['policy']['id'], 'user_id' => $user->id,
            'accepted_at' => now(), 'locale' => 'de_DE', 'content_hash' => null,
        ]);
        $this->getJson('/api/hawki/v1/announcements/actions/registration-policy', $this->headers())
            ->assertOk()->assertJsonPath('hash', $payload['policy']['hash']);
        DB::table('announcement_user')->update(['content_hash' => $payload['policy']['hash']]);
        $this->getJson('/api/hawki/v1/announcements/actions/registration-policy', $this->headers())
            ->assertOk()->assertJsonPath('policy', null)->assertJsonPath('consent', 'valid');
        $payload['policy'] = null;
        $this->postJson('/api/hawki/v1/auth/actions/complete-registration', $payload, $this->headers())
            ->assertOk()->assertJsonPath('data.attributes.keychain_state', 'initialized');
    }

    public function testMalformedRegistrationReturnsValidationErrors(): void
    {
        $this->startRegistration();
        $this->postJson('/api/hawki/v1/auth/actions/complete-registration', ['keychain' => ['set' => 'invalid']], $this->headers())
            ->assertStatus(422);
    }

    public function testPartialKeychainsCannotBeOverwrittenBySetup(): void
    {
        $payload = $this->registrationPayload();
        $user = $this->user();
        DB::table('user_keychain_values')->insert([
            'user_id' => $user->id, 'key' => 'privateKey', 'type' => 'private_key', 'value' => 'existing-secret',
        ]);
        $this->actingAs($user)->getJson('/api/hawki/v1/connections/hawki', $this->headers())
            ->assertOk()->assertJsonPath('data.attributes.keychain_state', 'inconsistent');
        $this->postJson('/api/hawki/v1/auth/actions/complete-registration', $payload, $this->headers())
            ->assertStatus(409)->assertJsonPath('errors.0.code', 'registration_keychain_inconsistent');
        self::assertSame('existing-secret', DB::table('user_keychain_values')->sole()->value);
        self::assertSame(0, DB::table('passkey_backups')->count());
    }

    public function testRemovedIdentityCanRegisterAgainUsingTheSameUserRow(): void
    {
        $payload = $this->registrationPayload();
        $user = $this->user();
        DB::table('users')->where('id', $user->id)->update(['isRemoved' => true, 'publicKey' => 'old-public-key']);
        $this->startRegistration();
        $this->postJson('/api/hawki/v1/auth/actions/complete-registration', $payload, $this->headers())
            ->assertOk()->assertJsonPath('data.attributes.keychain_state', 'initialized')
            ->assertJsonPath('data.attributes.userinfo.id', $user->id);
        self::assertSame(1, DB::table('users')->count());
        self::assertFalse((bool)DB::table('users')->sole()->isRemoved);
    }

    private function startRegistration(): void
    {
        $this->postJson('/api/hawki/v1/auth/actions/login', ['account' => 'alice', 'password' => 'test-password'], $this->headers())->assertOk();
    }

    private function registrationPayload(): array
    {
        $announcement = \App\Models\Announcements\Announcement::query()->create([
            'title' => 'Test policy', 'view' => 'basic-guidelines', 'type' => 'policy', 'is_global' => true,
        ]);
        $policy = $this->app->make(\App\Services\Announcements\RegistrationPolicyService::class)->resolve('de_DE');
        $value = (string)new \Hawk\HawkiCrypto\Value\SymmetricCryptoValue(str_repeat('i', 12), str_repeat('t', 16), 'encrypted-key-material');
        return [
            'policy' => ['id' => (string)$announcement->id, 'hash' => $policy->hash],
            'keychain' => ['publicKey' => 'test-public-key', 'set' => [
                ['key' => 'privateKey', 'type' => 'private_key', 'value' => $value],
                ['key' => 'publicKey', 'type' => 'public_key', 'value' => $value],
                ['key' => 'aiConvKey', 'type' => 'ai_conv', 'value' => $value],
            ]],
            'backup' => ['ciphertext' => base64_encode('encrypted-passkey'), 'iv' => base64_encode(str_repeat('i', 12)), 'tag' => base64_encode(str_repeat('t', 16))],
        ];
    }

    private function user(): User
    {
        return User::withoutEvents(fn() => User::query()->create([
            'username' => 'alice', 'name' => 'Alice', 'email' => 'alice@example.test',
            'employeetype' => 'employee', 'publicKey' => '', 'isRemoved' => false,
        ]));
    }
}
