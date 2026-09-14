<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\SystemSettings;
use App\Services\Config\EnvironmentConfigProxy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
class SystemSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private SystemSettings $settings;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('admin_settings')->delete();
        app(SystemSettings::class)->apply();
        config()->set([
            'app.name' => 'Deployment name', 'app.env' => 'testing', 'app.timezone' => 'CET',
            'app.locale' => 'en_US', 'locale.default_language' => 'en_US',
            'session.lifetime' => '120', 'session.encrypt' => false,
            'hawki.aiHandle' => '@hawki', 'hawki.accessibility.statement_url' => null,
            'filesystems.upload_limits.max_attachment_files' => '0',
        ]);
        $this->settings = new SystemSettings(new EnvironmentConfigProxy(config()));
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        // Restore process-wide timezone/environment before database rollback.
        app(SystemSettings::class)->reset('APP_TIMEZONE');
        app(SystemSettings::class)->reset('APP_ENV');
        parent::tearDown();
    }

    public function testReadingAndApplyingDefaultsDoesNotPopulateTheDatabase(): void
    {
        $this->settings->apply();
        $rows = $this->settings->rows();
        self::assertCount(count(SystemSettings::DEFINITIONS), $rows);
        self::assertSame(['environment'], array_values(array_unique(array_column($rows, 'source'))));
        self::assertSame(0, DB::table('admin_settings')->count());
    }

    public function testOnlyChangedValuesAreStoredAndSavingTheDefaultRemovesTheOverride(): void
    {
        $this->settings->save('APP_NAME', 'Deployment name', $this->user->id);
        self::assertSame(0, DB::table('admin_settings')->count());
        $this->settings->save('APP_NAME', 'Admin name', $this->user->id);
        self::assertSame(1, DB::table('admin_settings')->count());
        self::assertSame('Admin name', config('app.name'));
        $row = collect($this->settings->rows())->firstWhere('key', 'APP_NAME');
        self::assertSame('Deployment name', $row['default']);
        self::assertSame('database', $row['source']);
        $this->settings->save('APP_NAME', 'Deployment name', $this->user->id);
        self::assertSame(0, DB::table('admin_settings')->count());
        self::assertSame('Deployment name', config('app.name'));
    }

    public function testTypedDefaultsDoNotCreateSpuriousOverrides(): void
    {
        foreach (['APP_TIMEZONE' => 'CET', 'SESSION_LIFETIME' => 120, 'SESSION_ENCRYPT' => 0, 'MAX_ATTACHMENT_FILES' => 0, 'AI_MENTION_HANDLE' => 'hawki', 'ACCESSIBILITY_STATEMENT_URL' => ''] as $key => $value) {
            $this->settings->save($key, $value, $this->user->id);
        }
        self::assertSame(0, DB::table('admin_settings')->count());
    }

    public function testFalseZeroNullAndEmptyArrayOverridesAreNotLost(): void
    {
        config()->set([
            'session.encrypt' => true,
            'filesystems.upload_limits.max_attachment_files' => 5,
            'filesystems.upload_limits.allowed_file_mime_types' => ['image/png'],
            'hawki.accessibility.statement_url' => 'https://example.com/accessibility',
        ]);
        $settings = new SystemSettings(new EnvironmentConfigProxy(config()));
        foreach (['SESSION_ENCRYPT' => false, 'MAX_ATTACHMENT_FILES' => 0, 'ALLOWED_FILE_MIME_TYPES' => [], 'ACCESSIBILITY_STATEMENT_URL' => null] as $key => $value) {
            $settings->save($key, $value, $this->user->id);
            self::assertSame($value, config(SystemSettings::DEFINITIONS[$key][0]));
            self::assertSame('database', collect($settings->rows())->firstWhere('key', $key)['source']);
        }
        self::assertSame(4, DB::table('admin_settings')->count());
        $settings->reset('ACCESSIBILITY_STATEMENT_URL');
        self::assertSame('https://example.com/accessibility', config('hawki.accessibility.statement_url'));
    }

    public function testRefreshObservesAnotherProcessesChangesAndDeletion(): void
    {
        DB::table('admin_settings')->insert(['key' => 'APP_NAME', 'value' => json_encode('Other process')]);
        $this->settings->apply();
        self::assertSame('Other process', config('app.name'));
        DB::table('admin_settings')->where('key', 'APP_NAME')->delete();
        $this->settings->apply();
        self::assertSame('Deployment name', config('app.name'));
    }

    public function testLocaleAndRuntimeEnvironmentUseTheEffectiveValues(): void
    {
        $this->settings->save('APP_LOCALE', 'de_DE', $this->user->id);
        self::assertSame('de_DE', config('app.locale'));
        self::assertSame('de_DE', config('locale.default_language'));
        // Per-user locale middleware must not alter the system setting shown to admins.
        config(['app.locale' => 'en_US']);
        self::assertSame('de_DE', collect($this->settings->rows())->firstWhere('key', 'APP_LOCALE')['value']);
        $this->settings->reset('APP_LOCALE');
        self::assertSame('en_US', config('locale.default_language'));
        $this->settings->save('APP_TIMEZONE', 'Europe/Berlin', $this->user->id);
        self::assertSame('Europe/Berlin', date_default_timezone_get());
        $this->settings->save('APP_ENV', 'staging', $this->user->id);
        self::assertTrue(app()->environment('staging'));
    }

    public function testInvalidValuesNeverReachPersistence(): void
    {
        foreach (['APP_URL' => 'javascript:alert(1)', 'APP_TIMEZONE' => 'invalid', 'APP_LOCALE' => 'missing', 'AUTHENTICATION_METHOD' => 'ArbitraryClass', 'SESSION_LIFETIME' => 0, 'SESSION_ENCRYPT' => 'false'] as $key => $value) {
            try {
                $this->settings->save($key, $value, $this->user->id);
                self::fail('Accepted invalid setting: ' . $key);
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('value', $exception->errors());
            }
        }
        self::assertSame(0, DB::table('admin_settings')->count());
    }

    public function testEnvironmentRowsAreUniqueAndUnknownKeysCannotOverrideSecrets(): void
    {
        DB::table('admin_settings')->insert(['key' => 'APP_KEY', 'value' => json_encode('ignored')]);
        $key = config('app.key');
        $this->settings->apply();
        self::assertSame($key, config('app.key'));
        $rows = $this->settings->environment();
        self::assertCount(count($rows), array_unique(array_column($rows, 'id')));
        self::assertSame('[set]', collect($rows)->firstWhere('key', 'APP_KEY')['value']);
    }
}
