<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class BrandFontApiTest extends TestCase
{
    use DatabaseTransactions;
    use \Tests\Support\AdminJsonApiRequests;
    private const SETTINGS = '/api/hawki/v1/admin-settings';
    private const FONTS = '/api/hawki/v1/brand-fonts/';
    /** A WOFF2 header without tables: enough to be accepted as a (static) font. */
    private const FONT = "wOF2\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.avatar_storage'));
    }

    public function testAnAdministratorUploadsAFontSavesItAndGuestsCanLoadIt(): void
    {
        $this->actingAs(User::factory()->create(['employeetype' => 'admin']));
        $file = $this->post(self::SETTINGS . '/actions/font-upload', ['font' => UploadedFile::fake()->createWithContent('Brand.woff2', self::FONT)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('font.variable', false)->json('font.file');
        self::assertSame(hash('sha256', self::FONT) . '.woff2', $file);

        $version = $this->getJson(self::SETTINGS, ['Accept' => 'application/vnd.api+json'])->collect('data')->firstWhere('id', 'BRAND_FONT')['meta']['version'];
        $this->patchAdminResource(self::SETTINGS . '/BRAND_FONT', ['version' => $version, 'values' => ['value' => $file]])->assertOk();
        self::assertSame($file, config('hawki.branding.font'));

        // A name that was never uploaded is refused, also when it looks like a stored file.
        $version = $this->getJson(self::SETTINGS, ['Accept' => 'application/vnd.api+json'])->collect('data')->firstWhere('id', 'BRAND_FONT')['meta']['version'];
        $this->patchAdminResource(self::SETTINGS . '/BRAND_FONT', ['version' => $version, 'values' => ['value' => str_repeat('0', 64) . '.woff2']])->assertStatus(422);
        self::assertSame($file, config('hawki.branding.font'));

        $this->app['auth']->forgetGuards();
        $response = $this->get(self::FONTS . $file)->assertOk()->assertHeader('Content-Type', 'font/woff2');
        self::assertSame(self::FONT, file_get_contents($response->baseResponse->getFile()->getPathname()));
        // Byte ranges are answered, which Safari relies on for media.
        $this->get(self::FONTS . $file, ['Range' => 'bytes=0-3'])->assertStatus(206);
        self::assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->get(self::FONTS . str_repeat('0', 64) . '.woff2')->assertNotFound();

        // A desktop format is stored and served as what it is.
        $this->actingAs(User::factory()->create(['employeetype' => 'admin']));
        $otf = 'OTTO' . str_repeat("\0", 8);
        $file = $this->post(self::SETTINGS . '/actions/font-upload', ['font' => UploadedFile::fake()->createWithContent('Brand.otf', $otf)], ['Accept' => 'application/json'])
            ->assertOk()->json('font.file');
        self::assertSame(hash('sha256', $otf) . '.otf', $file);
        $this->get(self::FONTS . $file)->assertOk()->assertHeader('Content-Type', 'font/otf');
    }

    public function testReplacingASavedFontRemovesTheOldFile(): void
    {
        $this->actingAs(User::factory()->create(['employeetype' => 'admin']));
        $disk = Storage::disk(config('filesystems.avatar_storage'));
        $save = function (string $file): void {
            $version = $this->getJson(self::SETTINGS, ['Accept' => 'application/vnd.api+json'])->collect('data')->firstWhere('id', 'BRAND_FONT')['meta']['version'];
            $this->patchAdminResource(self::SETTINGS . '/BRAND_FONT', ['version' => $version, 'values' => ['value' => $file]])->assertOk();
        };
        $old = str_repeat('a', 64) . '.woff2';
        $new = str_repeat('b', 64) . '.woff2';
        $disk->put('branding/fonts/' . $old, 'wOF2');
        $disk->put('branding/fonts/' . $new, 'wOF2');
        touch($disk->path('branding/fonts/' . $old), time() - 2 * 86400);

        $save($old);
        self::assertTrue($disk->exists('branding/fonts/' . $old));
        $save($new);
        self::assertFalse($disk->exists('branding/fonts/' . $old));
        self::assertTrue($disk->exists('branding/fonts/' . $new));
    }

    public function testOnlyAdministratorsCanUploadAndOnlyFontsAreAccepted(): void
    {
        $upload = fn (string $name, string $content) => $this->post(self::SETTINGS . '/actions/font-upload', ['font' => UploadedFile::fake()->createWithContent($name, $content)], ['Accept' => 'application/json']);

        $this->actingAs(User::factory()->create(['employeetype' => 'tester']));
        $upload('Brand.woff2', self::FONT)->assertForbidden();

        $this->actingAs(User::factory()->create(['employeetype' => 'admin']));
        $upload('Brand.woff2', '<html>not a font</html>')->assertStatus(422);
        $upload('Brand.exe', self::FONT)->assertStatus(422);
        self::assertSame([], Storage::disk(config('filesystems.avatar_storage'))->allFiles());
    }
}
