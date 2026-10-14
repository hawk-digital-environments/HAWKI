<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\BrandMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(BrandMedia::class)]
class BrandMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.avatar_storage'));
    }

    public function testFormatsAreRecognizedByContent(): void
    {
        $iso = static fn (string $brand): string => "\0\0\0\x18ftyp" . $brand . str_repeat("\0", 8);

        foreach ([
            'jpg' => "\xFF\xD8\xFF\xE0",
            'png' => "\x89PNG\r\n\x1A\n",
            'webp' => "RIFF\0\0\0\0WEBPVP8 ",
            'avif' => $iso('avif'),
            'mp4' => $iso('isom'),
            'webm' => "\x1A\x45\xDF\xA3",
        ] as $format => $bytes) {
            static::assertSame($format, BrandMedia::format($bytes), $format);
        }

        foreach ([$iso('heic'), '<svg xmlns="http://www.w3.org/2000/svg"/>', 'GIF89a', ''] as $bytes) {
            static::assertNull(BrandMedia::format($bytes));
        }
    }

    public function testItStoresByContentAndTellsImagesFromVideos(): void
    {
        $video = "\0\0\0\x18ftypmp42" . str_repeat("\0", 8);
        // The name it was uploaded under does not decide the format.
        $stored = (new BrandMedia())->store(UploadedFile::fake()->createWithContent('clip.jpg', $video));

        static::assertSame(hash('sha256', $video) . '.mp4', $stored['file']);
        static::assertSame('video', $stored['kind']);
        static::assertSame(['video/mp4', 'image'], [BrandMedia::mediaType($stored['file']), BrandMedia::kind(str_repeat('a', 64) . '.png')]);
    }

    public function testItRejectsOtherFiles(): void
    {
        $this->expectException(ValidationException::class);
        (new BrandMedia())->store(UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"/>'));
    }

    public function testPruneKeepsTheBackgroundsInUse(): void
    {
        $disk = Storage::disk(config('filesystems.avatar_storage'));
        $used = str_repeat('a', 64) . '.jpg';
        $old = str_repeat('b', 64) . '.mp4';

        foreach ([$used, $old] as $file) {
            $disk->put('branding/media/' . $file, 'x');
            touch($disk->path('branding/media/' . $file), time() - 2 * 86400);
        }

        config(['hawki.branding.login_backgrounds' => [['source' => 'upload', 'file' => $used, 'credit' => null, 'link' => null]]]);

        static::assertSame(1, (new BrandMedia())->prune());
        static::assertTrue((new BrandMedia())->exists($used));
        static::assertFalse((new BrandMedia())->exists($old));
    }
}
