<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Container;

use App\Services\System\Container\SystemPaths;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SystemPaths::class)]
class SystemPathsTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $app = $this->createMock(Application::class);
        $sut = new SystemPaths($app);

        static::assertInstanceOf(SystemPaths::class, $sut);
    }

    // =========================================================================
    // path
    // =========================================================================

    public function testItDelegatesToApplicationPath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('path')->with('')->willReturn('/var/www/app');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/app', $sut->path());
    }

    public function testItPassesSubpathToApplicationPath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())
            ->method('path')
            ->with('Services/Ai')
            ->willReturn('/var/www/app/Services/Ai');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/app/Services/Ai', $sut->path('Services/Ai'));
    }

    // =========================================================================
    // basePath
    // =========================================================================

    public function testItDelegatesToApplicationBasePath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('basePath')->with('')->willReturn('/var/www');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www', $sut->basePath());
    }

    public function testItPassesSubpathToApplicationBasePath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())
            ->method('basePath')
            ->with('composer.json')
            ->willReturn('/var/www/composer.json');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/composer.json', $sut->basePath('composer.json'));
    }

    // =========================================================================
    // bootstrapPath
    // =========================================================================

    public function testItDelegatesToApplicationBootstrapPath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('bootstrapPath')->with('')->willReturn('/var/www/bootstrap');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/bootstrap', $sut->bootstrapPath());
    }

    public function testItPassesSubpathToApplicationBootstrapPath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())
            ->method('bootstrapPath')
            ->with('cache')
            ->willReturn('/var/www/bootstrap/cache');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/bootstrap/cache', $sut->bootstrapPath('cache'));
    }

    // =========================================================================
    // getBootstrapProvidersPath
    // =========================================================================

    public function testItDelegatesToApplicationGetBootstrapProvidersPath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())
            ->method('getBootstrapProvidersPath')
            ->willReturn('/var/www/bootstrap/providers.php');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/bootstrap/providers.php', $sut->getBootstrapProvidersPath());
    }

    // =========================================================================
    // configPath
    // =========================================================================

    public function testItDelegatesToApplicationConfigPath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('configPath')->with('')->willReturn('/var/www/config');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/config', $sut->configPath());
    }

    public function testItPassesSubpathToApplicationConfigPath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())
            ->method('configPath')
            ->with('app.php')
            ->willReturn('/var/www/config/app.php');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/config/app.php', $sut->configPath('app.php'));
    }

    // =========================================================================
    // databasePath
    // =========================================================================

    public function testItDelegatesToApplicationDatabasePath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('databasePath')->with('')->willReturn('/var/www/database');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/database', $sut->databasePath());
    }

    public function testItPassesSubpathToApplicationDatabasePath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())
            ->method('databasePath')
            ->with('migrations')
            ->willReturn('/var/www/database/migrations');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/database/migrations', $sut->databasePath('migrations'));
    }

    // =========================================================================
    // publicPath
    // =========================================================================

    public function testItDelegatesToApplicationPublicPath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('publicPath')->with('')->willReturn('/var/www/public');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/public', $sut->publicPath());
    }

    public function testItPassesSubpathToApplicationPublicPath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())
            ->method('publicPath')
            ->with('index.php')
            ->willReturn('/var/www/public/index.php');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/public/index.php', $sut->publicPath('index.php'));
    }

    // =========================================================================
    // storagePath
    // =========================================================================

    public function testItDelegatesToApplicationStoragePath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('storagePath')->with('')->willReturn('/var/www/storage');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/storage', $sut->storagePath());
    }

    public function testItPassesSubpathToApplicationStoragePath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())
            ->method('storagePath')
            ->with('app/data/file.json')
            ->willReturn('/var/www/storage/app/data/file.json');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/storage/app/data/file.json', $sut->storagePath('app/data/file.json'));
    }

    // =========================================================================
    // resourcePath
    // =========================================================================

    public function testItDelegatesToApplicationResourcePath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('resourcePath')->with('')->willReturn('/var/www/resources');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/resources', $sut->resourcePath());
    }

    public function testItPassesSubpathToApplicationResourcePath(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())
            ->method('resourcePath')
            ->with('static_llm_data/lite_llm')
            ->willReturn('/var/www/resources/static_llm_data/lite_llm');

        $sut = new SystemPaths($app);

        static::assertSame('/var/www/resources/static_llm_data/lite_llm', $sut->resourcePath('static_llm_data/lite_llm'));
    }
}
