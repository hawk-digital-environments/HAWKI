<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Config;

use App\Services\Frontend\Config\SecurityConfig;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SecurityConfig::class)]
class SecurityConfigTest extends TestCase
{
    // =========================================================================
    // make
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = SecurityConfig::make($this->repo());
        static::assertInstanceOf(SecurityConfig::class, $sut);
    }

    public function testAutomaticPasskeyGenerationDefaultsToDisabled(): void
    {
        $sut = SecurityConfig::make(new Repository());
        static::assertFalse($sut->passkeyAutoGenerate);
        static::assertFalse($sut->toPublicArray(Request::create('/'))['passkeyAutoGenerate']);
    }

    public function testAutomaticPasskeyGenerationIsExposedToTheFrontend(): void
    {
        $repo = $this->repo();
        $repo->set('hawki.security.passkey.auto_generate', true);
        $sut = SecurityConfig::make($repo);
        static::assertTrue($sut->passkeyAutoGenerate);
        static::assertTrue($sut->toPublicArray(Request::create('/'))['passkeyAutoGenerate']);
    }

    public function testItDefaultsPasskeyAllowPasteToTrue(): void
    {
        $sut = SecurityConfig::make($this->repo());
        static::assertTrue($sut->passkeyAllowPaste);
    }

    public function testItDefaultsPasskeyRestrictCharactersToTrue(): void
    {
        $sut = SecurityConfig::make($this->repo());
        static::assertTrue($sut->passkeyRestrictCharacters);
    }

    public function testItReadsPasskeyAllowPasteFromConfig(): void
    {
        $sut = SecurityConfig::make($this->repo(allowPaste: false));
        static::assertFalse($sut->passkeyAllowPaste);
    }

    public function testItReadsPasskeyRestrictCharactersFromConfig(): void
    {
        $sut = SecurityConfig::make($this->repo(restrictCharacters: false));
        static::assertFalse($sut->passkeyRestrictCharacters);
    }

    // =========================================================================
    // publicKey
    // =========================================================================

    public function testItReturnsCorrectPublicKey(): void
    {
        static::assertSame('security', SecurityConfig::publicKey());
    }

    // =========================================================================
    // toPublicArray
    // =========================================================================

    public function testItToPublicArrayContainsPasskeyAllowPaste(): void
    {
        $sut = SecurityConfig::make($this->repo(allowPaste: false));
        $result = $sut->toPublicArray(Request::create('/'));
        static::assertArrayHasKey('passkeyAllowPaste', $result);
        static::assertFalse($result['passkeyAllowPaste']);
    }

    public function testItToPublicArrayContainsPasskeyRestrictCharacters(): void
    {
        $sut = SecurityConfig::make($this->repo(restrictCharacters: false));
        $result = $sut->toPublicArray(Request::create('/'));
        static::assertArrayHasKey('passkeyRestrictCharacters', $result);
        static::assertFalse($result['passkeyRestrictCharacters']);
    }

    public function testItToPublicArrayIsNotNullRegardlessOfAuthState(): void
    {
        $sut = SecurityConfig::make($this->repo());
        static::assertNotNull($sut->toPublicArray(Request::create('/')));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function repo(bool $allowPaste = true, bool $restrictCharacters = true): Repository
    {
        return new Repository([
            'hawki' => [
                'security' => [
                    'passkey' => [
                        'allow_paste' => $allowPaste,
                        'char_limitation' => $restrictCharacters,
                    ],
                ],
            ],
        ]);
    }
}
