<?php

namespace Tests\Unit;

use App\Models\Assistants\AssistantAvatar;
use App\Services\Assistant\AssistantAvatarUrlResolver;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssistantAvatarUrlResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function storeAvatarFile(string $uuid): void
    {
        app(AvatarStorageService::class)->store(
            'png-contents',
            "{$uuid}.png",
            $uuid,
            AssistantAvatar::STORAGE_CATEGORY,
        );
    }

    public function test_returns_null_for_null_uuid(): void
    {
        $resolver = app(AssistantAvatarUrlResolver::class);

        $this->assertNull($resolver->forUuid(null));
    }

    public function test_returns_null_for_empty_uuid(): void
    {
        $resolver = app(AssistantAvatarUrlResolver::class);

        $this->assertNull($resolver->forUuid(''));
    }

    public function test_resolves_url_when_file_exists(): void
    {
        $uuid = Str::uuid()->toString();
        $this->storeAvatarFile($uuid);

        $resolver = app(AssistantAvatarUrlResolver::class);

        $url = $resolver->forUuid($uuid);

        $this->assertNotNull($url);
        $this->assertStringContainsString($uuid, $url);
    }

    public function test_returns_null_when_no_file_exists_for_uuid(): void
    {
        $resolver = app(AssistantAvatarUrlResolver::class);

        $this->assertNull($resolver->forUuid(Str::uuid()->toString()));
    }
}
