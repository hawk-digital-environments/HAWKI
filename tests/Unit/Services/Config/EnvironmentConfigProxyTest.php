<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Config;

use App\Services\Config\EnvironmentConfigProxy;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnvironmentConfigProxy::class)]
class EnvironmentConfigProxyTest extends TestCase
{
    public function testDefaultsSurviveRepeatedCaptureAndRemovedOverrides(): void
    {
        $config = new Repository(['app' => ['name' => 'Deployment'], 'optional' => null, 'unrelated' => 'original']);
        $proxy = new EnvironmentConfigProxy($config);
        $proxy->capture(['app.name', 'optional']);
        $proxy->apply(['app.name' => 'Override', 'optional' => false]);
        $proxy->capture(['app.name', 'optional']);
        $config->set('unrelated', 'changed');
        $proxy->apply([]);
        self::assertSame('Deployment', $config->get('app.name'));
        self::assertNull($config->get('optional'));
        self::assertSame('changed', $config->get('unrelated'));
    }

    public function testAnUnregisteredPathCannotChangeTheConfig(): void
    {
        $config = new Repository(['app' => ['key' => 'secret']]);
        $proxy = new EnvironmentConfigProxy($config);
        try {
            $proxy->apply(['app.key' => 'replacement']);
            self::fail('Accepted an unregistered path.');
        } catch (\InvalidArgumentException) {
            self::assertSame('secret', $config->get('app.key'));
        }
    }
}
