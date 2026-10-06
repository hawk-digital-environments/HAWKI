<?php
declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[CoversNothing]
class SystemSettingsConfigCacheTest extends TestCase
{
    public function testCachedConfigurationKeepsDeploymentDefaultsAndReadsLiveOverrides(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'settings-db-');
        $cache = tempnam(sys_get_temp_dir(), 'settings-cache-');
        unlink($cache);
        $script = <<<'CODE'
require 'vendor/autoload.php';
$pdo = new PDO('sqlite:' . getenv('DB_DATABASE'));
$pdo->exec('CREATE TABLE admin_settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
$pdo->prepare('INSERT INTO admin_settings (key, value) VALUES (?, ?)')->execute(['APP_NAME', json_encode('Database override')]);
$boot = function () {
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    return $app;
};
$app = $boot();
if (config('app.name') !== 'Database override') throw new RuntimeException('Initial override missing');
$app->make(Illuminate\Contracts\Console\Kernel::class)->call('config:cache');
$cached = require getenv('APP_CONFIG_CACHE');
if ($cached['app']['name'] !== 'Deployment baseline') throw new RuntimeException('Override leaked into config cache');
$boot();
if (config('app.name') !== 'Database override') throw new RuntimeException('Cached boot ignored live override');
$pdo->exec('DELETE FROM admin_settings');
$boot();
if (config('app.name') !== 'Deployment baseline') throw new RuntimeException('Reset did not restore cached deployment default');
echo 'cache override lifecycle passed';
CODE;
        try {
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), [
                'APP_CONFIG_CACHE' => $cache, 'APP_NAME' => 'Deployment baseline', 'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => false,
                'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
            ]);
            $process->setTimeout(60)->run();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput() . $process->getOutput());
            self::assertStringContainsString('cache override lifecycle passed', $process->getOutput());
        } finally {
            foreach ([$database, $database . '-wal', $database . '-shm', $cache] as $file) {
                if (is_file($file)) unlink($file);
            }
        }
    }
}
