<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Migrations\Make;

use App\Services\Frontend\Migrations\Make\BackendMigrationCreator;
use App\Services\Frontend\Migrations\Make\FrontendMigrationCreator;
use App\Services\Frontend\Migrations\Make\JsMigrationCreator;
use App\Services\Frontend\Migrations\Make\PluginMigrationHookEnsurer;
use App\Services\System\Time\CarbonClock;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FrontendMigrationCreator::class)]
class FrontendMigrationCreatorTest extends TestCase
{
    // =========================================================================
    // Constants
    // =========================================================================

    public function testItDefinesRunTypeAfterLogin(): void
    {
        static::assertSame('after_login', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN);
    }

    public function testItDefinesRunTypeAfterPasskey(): void
    {
        static::assertSame('after_passkey', FrontendMigrationCreator::RUN_TYPE_AFTER_PASSKEY);
    }

    // =========================================================================
    // create
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();
        static::assertInstanceOf(FrontendMigrationCreator::class, $sut);
    }

    public function testItReturnsPathsAndPluginStatus(): void
    {
        $sut = $this->makeSut();
        $result = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN, 'test_plugin');
        static::assertArrayHasKey('backendPath', $result);
        static::assertArrayHasKey('jsPath', $result);
        static::assertArrayHasKey('pluginPath', $result);
        static::assertArrayHasKey('pluginStatus', $result);
    }

    public function testItReturnsPhpPathAsBackendPath(): void
    {
        $sut = $this->makeSut();
        $result = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN, 'test_plugin');
        static::assertStringEndsWith('.php', $result['backendPath']);
    }

    public function testItReturnsTsPathAsJsPath(): void
    {
        $sut = $this->makeSut();
        $result = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN, 'test_plugin');
        static::assertStringEndsWith('.ts', $result['jsPath']);
    }

    public function testItIncludesTimestampInMigrationName(): void
    {
        $sut = $this->makeSut(new \DateTimeImmutable('2024-01-15 12:00:00'));
        $result = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN, 'test_plugin');
        static::assertStringContainsString('2024_01_15_120000', $result['backendPath']);
    }

    public function testItIncludesRunTypeInMigrationName(): void
    {
        $sut = $this->makeSut();
        $result = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN, 'test_plugin');
        static::assertStringContainsString('after_login', $result['backendPath']);
    }

    public function testItIncludesNameInMigrationName(): void
    {
        $sut = $this->makeSut();
        $result = $sut->create('my_widget', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN, 'test_plugin');
        static::assertStringContainsString('my_widget', $result['backendPath']);
    }

    public function testItConvertsNameToSnakeCase(): void
    {
        $sut = $this->makeSut();
        $result = $sut->create('myWidget', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN, 'test_plugin');
        static::assertStringContainsString('my_widget', $result['backendPath']);
    }

    public function testItConvertsCamelCaseRunTypeToSnakeCase(): void
    {
        $sut = $this->makeSut();
        $result = $sut->create('test_migration', 'afterLogin', 'test_plugin');
        static::assertStringContainsString('after_login', $result['backendPath']);
    }

    public function testJsMigrationGoesIntoPluginRunTypeSubfolder(): void
    {
        $sut = $this->makeSut();
        $result = $sut->create('test_migration', FrontendMigrationCreator::RUN_TYPE_AFTER_PASSKEY, 'test_plugin');
        static::assertStringContainsString('plugins/test_plugin/migrations/after_passkey', $result['jsPath']);
    }

    public function testBothFilesShareTheSameMigrationName(): void
    {
        $sut = $this->makeSut(new \DateTimeImmutable('2024-06-01 09:30:00'));
        $result = $sut->create('update_user', FrontendMigrationCreator::RUN_TYPE_AFTER_LOGIN, 'test_plugin');

        $phpBasename = pathinfo($result['backendPath'], PATHINFO_FILENAME);
        $tsBasename = pathinfo($result['jsPath'], PATHINFO_FILENAME);
        static::assertSame($phpBasename, $tsBasename);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(?\DateTimeImmutable $now = null): FrontendMigrationCreator
    {
        $files = $this->createMock(Filesystem::class);
        $files->method('get')->willReturn('');

        $pluginFiles = $this->createMock(Filesystem::class);
        $pluginFiles->method('glob')->willReturn(['/tmp/js/plugins/test_plugin/test_plugin.plugin.ts']);
        $pluginFiles->method('get')->willReturn('');

        $clock = new CarbonClock($now ?? new \DateTimeImmutable('2024-01-01 00:00:00'));

        return new FrontendMigrationCreator(
            phpMigrationCreator: new BackendMigrationCreator($files),
            jsMigrationCreator: new JsMigrationCreator($files),
            clock: $clock,
            pluginMigrationHookEnsurer: new PluginMigrationHookEnsurer($pluginFiles)
        );
    }
}