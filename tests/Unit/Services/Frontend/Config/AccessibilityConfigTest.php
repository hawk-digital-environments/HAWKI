<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Config;

use App\Services\Frontend\Config\AccessibilityConfig;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AccessibilityConfig::class)]
class AccessibilityConfigTest extends TestCase
{
    public function testItReadsAndTrimsTheStatementUrl(): void
    {
        $sut = AccessibilityConfig::make($this->repo('  https://example.test/accessibility  '));

        static::assertSame('https://example.test/accessibility', $sut->statementUrl);
    }

    public function testItUsesNullWhenTheStatementUrlIsEmpty(): void
    {
        $sut = AccessibilityConfig::make($this->repo('   '));

        static::assertNull($sut->statementUrl);
    }

    public function testItRejectsInvalidStatementUrls(): void
    {
        foreach (['javascript:alert(1)', '/accessibility', 'accessibility'] as $url) {
            $sut = AccessibilityConfig::make($this->repo($url));

            static::assertNull($sut->statementUrl);
        }
    }

    public function testItReturnsThePublicKey(): void
    {
        static::assertSame('accessibility', AccessibilityConfig::publicKey());
    }

    public function testItExposesTheStatementUrlToGuests(): void
    {
        $sut = AccessibilityConfig::make($this->repo('https://example.test/accessibility'));

        static::assertSame(
            ['statementUrl' => 'https://example.test/accessibility'],
            $sut->toPublicArray(Request::create('/')),
        );
    }

    private function repo(?string $statementUrl): Repository
    {
        return new Repository([
            'hawki' => [
                'accessibility' => [
                    'statement_url' => $statementUrl,
                ],
            ],
        ]);
    }
}
