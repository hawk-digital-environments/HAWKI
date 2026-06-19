<?php

namespace Tests\Feature;

use App\Models\Assistants\AssistantAvatar;
use App\Services\Storage\AvatarStorageService;
use Database\Seeders\AssistantAvatarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssistantAvatarSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_seeder_creates_avatars_from_source_images(): void
    {
        $expected = $this->expectedSourceNames();

        $this->seed(AssistantAvatarSeeder::class);

        $this->assertSame(count($expected), AssistantAvatar::count());
        foreach ($expected as $name) {
            $this->assertDatabaseHas('assistant_avatars', ['name' => $name]);
        }
    }

    public function test_seeder_stores_files_with_resolvable_urls(): void
    {
        $this->seed(AssistantAvatarSeeder::class);

        $avatar = AssistantAvatar::first();
        $this->assertNotNull($avatar);

        $url = app(AvatarStorageService::class)->getUrl($avatar->uuid, AssistantAvatar::STORAGE_CATEGORY);
        $this->assertNotNull($url);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(AssistantAvatarSeeder::class);
        $countAfterFirstRun = AssistantAvatar::count();
        $firstAvatar = AssistantAvatar::first();
        $this->assertNotNull($firstAvatar);

        $this->seed(AssistantAvatarSeeder::class);
        $countAfterSecondRun = AssistantAvatar::count();
        $secondUuid = AssistantAvatar::where('name', $firstAvatar->name)->value('uuid');

        $this->assertEquals($countAfterFirstRun, $countAfterSecondRun);
        $this->assertEquals($firstAvatar->uuid, $secondUuid);
    }

    private function expectedSourceNames(): array
    {
        $dir = public_path('img/defaults/assistant_avatars');
        $files = glob($dir . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE);

        return collect($files ?: [])
            ->map(fn (string $file) => pathinfo($file, PATHINFO_FILENAME))
            ->values()
            ->all();
    }
}
