<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Config;

use App\Services\Frontend\Config\BrandingConfig;
use App\Services\Frontend\Config\LegalConfig;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(BrandingConfig::class)]
#[CoversClass(LegalConfig::class)]
class BrandingConfigTest extends TestCase
{
    public function testItExposesNamingLogoAndLegalLinksToGuests(): void
    {
        $repo = new Repository([
            'app' => ['name' => 'UniGPT'],
            'hawki' => [
                'branding' => ['logo' => '<svg xmlns="http://www.w3.org/2000/svg"/>', 'organization_name' => ' Example University ', 'organization_url' => 'https://example.edu'],
                'legal' => ['imprint_url' => 'https://example.edu/imprint', 'privacy_url' => '/dataprotection', 'terms_url' => null],
            ],
        ]);

        static::assertSame(
            ['appName' => 'UniGPT', 'logoUrl' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>'), 'organizationName' => 'Example University', 'organizationUrl' => 'https://example.edu'],
            BrandingConfig::make($repo)->toPublicArray(Request::create('/')),
        );
        static::assertSame(
            ['imprintUrl' => 'https://example.edu/imprint', 'privacyUrl' => '/dataprotection', 'termsUrl' => null],
            LegalConfig::make($repo)->toPublicArray(Request::create('/')),
        );
    }

    public function testLinksFromTheDeploymentAreCheckedAgain(): void
    {
        foreach (['javascript:alert(1)', '//evil.example/x', 'imprint', '/a b', 42, null] as $value) {
            static::assertNull(LegalConfig::link($value));
        }
    }

    public function testItReturnsThePublicKeys(): void
    {
        static::assertSame('branding', BrandingConfig::publicKey());
        static::assertSame('legal', LegalConfig::publicKey());
    }
}
