<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Config;

use App\Services\Frontend\Config\BrandingConfig;
use App\Services\Frontend\Config\LegalConfig;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(BrandingConfig::class)]
#[CoversClass(LegalConfig::class)]
class BrandingConfigTest extends TestCase
{
    public function testItExposesNamingLogoAndLegalLinksToGuests(): void
    {
        Storage::fake(config('filesystems.avatar_storage'));
        Storage::disk(config('filesystems.avatar_storage'))->put('branding/media/' . str_repeat('a', 64) . '.mp4', 'x');
        $repo = new Repository([
            'app' => ['name' => 'UniGPT'],
            'hawki' => [
                'branding' => ['logo' => '<svg xmlns="http://www.w3.org/2000/svg"/>', 'organization_name' => ' Example University ', 'organization_url' => 'https://example.edu',
                    'login_backgrounds' => [
                        ['source' => 'upload', 'file' => str_repeat('a', 64) . '.mp4', 'credit' => ' Photo: A. Person ', 'link' => null],
                        // Entries whose file does not exist are left out.
                        ['source' => 'upload', 'file' => str_repeat('b', 64) . '.jpg', 'credit' => null, 'link' => null],
                        ['source' => 'builtin', 'file' => '../x.mp4', 'credit' => null, 'link' => null],
                    ]],
                'legal' => ['imprint_url' => 'https://example.edu/imprint', 'privacy_url' => '/dataprotection', 'terms_url' => null],
            ],
        ]);

        static::assertSame(
            ['appName' => 'UniGPT', 'logoUrl' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>'),
                'loginBackgrounds' => [['url' => '/api/hawki/v1/brand-media/' . str_repeat('a', 64) . '.mp4', 'kind' => 'video', 'credit' => 'Photo: A. Person', 'link' => null]],
                'organizationName' => 'Example University', 'organizationUrl' => 'https://example.edu'],
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

    public function testTheBuiltInVideosAreTheDefaultBackgrounds(): void
    {
        // Read from the config file itself: the running configuration carries whatever the local database overrides.
        $default = (require config_path('hawki.php'))['branding']['login_backgrounds'];
        $backgrounds = BrandingConfig::make(new Repository(['app' => ['name' => 'HAWKI'], 'hawki' => ['branding' => ['login_backgrounds' => $default]]]))->loginBackgrounds;

        static::assertNotEmpty($backgrounds);
        static::assertStringStartsWith('/bg_videos/', $backgrounds[0]['url']);
        static::assertSame(['video'], array_values(array_unique(array_column($backgrounds, 'kind'))));
    }

    public function testItReturnsThePublicKeys(): void
    {
        static::assertSame('branding', BrandingConfig::publicKey());
        static::assertSame('legal', LegalConfig::publicKey());
    }
}
