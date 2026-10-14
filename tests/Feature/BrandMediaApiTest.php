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
class BrandMediaApiTest extends TestCase
{
    use DatabaseTransactions;
    use \Tests\Support\AdminJsonApiRequests;
    private const SETTINGS = '/api/hawki/v1/admin-settings';
    private const MEDIA = '/api/hawki/v1/brand-media/';
    private const VIDEO = "\0\0\0\x18ftypisom\0\0\0\0\0\0\0\0";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.avatar_storage'));
    }

    public function testAnUploadedBackgroundIsSavedAndPlaysForGuestsWithByteRanges(): void
    {
        $this->actingAs(User::factory()->create(['employeetype' => 'admin']));
        $upload = $this->post(self::SETTINGS . '/actions/media-upload', ['media' => UploadedFile::fake()->createWithContent('Campus.mp4', self::VIDEO)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('media.kind', 'video');
        $file = $upload->json('media.file');

        $save = function (array $backgrounds) {
            $version = $this->getJson(self::SETTINGS, ['Accept' => 'application/vnd.api+json'])->collect('data')->firstWhere('id', 'BRAND_LOGIN_BACKGROUNDS')['meta']['version'];

            return $this->patchAdminResource(self::SETTINGS . '/BRAND_LOGIN_BACKGROUNDS', ['version' => $version, 'values' => ['value' => $backgrounds]]);
        };
        $entry = ['source' => 'upload', 'file' => $file, 'credit' => ' Campus ', 'link' => 'https://example.edu'];
        $save([$entry])->assertOk();
        self::assertSame([array_replace($entry, ['credit' => 'Campus'])], config('hawki.branding.login_backgrounds'));

        // A file that was never uploaded, a path outside the built-in folder or a script link is refused.
        $save([['file' => str_repeat('0', 64) . '.jpg'] + $entry])->assertStatus(422);
        $save([['source' => 'builtin', 'file' => '../../.env'] + $entry])->assertStatus(422);
        $save([['link' => 'javascript:alert(1)'] + $entry])->assertStatus(422);
        // An empty list is allowed: the sign-in page then shows only the logo.
        $save([])->assertOk();
        self::assertSame([], config('hawki.branding.login_backgrounds'));

        $this->app['auth']->forgetGuards();
        $this->get(self::MEDIA . $file)->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get(self::MEDIA . $file, ['Range' => 'bytes=0-7'])->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-7/' . \strlen(self::VIDEO));
        $this->get(self::MEDIA . str_repeat('0', 64) . '.jpg')->assertNotFound();
    }

    public function testOnlyAdministratorsCanUploadAndOnlyImagesOrVideosAreAccepted(): void
    {
        $upload = fn (string $name, string $content) => $this->post(self::SETTINGS . '/actions/media-upload', ['media' => UploadedFile::fake()->createWithContent($name, $content)], ['Accept' => 'application/json']);

        $this->actingAs(User::factory()->create(['employeetype' => 'tester']));
        $upload('Campus.mp4', self::VIDEO)->assertForbidden();

        $this->actingAs(User::factory()->create(['employeetype' => 'admin']));
        $upload('Campus.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')->assertStatus(422);
        $upload('Campus.png', '<html>not an image</html>')->assertStatus(422);
        self::assertSame([], Storage::disk(config('filesystems.avatar_storage'))->allFiles());
    }
}
