<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Http\Controllers\StorageProxyController;
use App\Models\User;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(StorageProxyController::class)]
class StorageProxyAttachmentAccessTest extends TestCase
{
    use DatabaseTransactions;
    use ChatAttachmentFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Config::string('filesystems.file_storage'));
    }

    public function testOwnerCanDownloadPrivateAttachment(): void
    {
        $owner = User::factory()->create();
        $identifier = $this->storeFile(StoredFileCategory::PRIVATE);
        $this->createPrivateAttachment($owner, $identifier->uuid);

        $response = $this->actingAs($owner)->get($this->proxyUrl($identifier));

        $response->assertOk();
        self::assertSame('top secret', $response->streamedContent());
        self::assertStringContainsString('private', (string)$response->headers->get('Cache-Control'));
        self::assertStringNotContainsString('public', (string)$response->headers->get('Cache-Control'));
    }

    public function testOtherUserCannotDownloadPrivateAttachment(): void
    {
        $owner = User::factory()->create();
        $identifier = $this->storeFile(StoredFileCategory::PRIVATE);
        $this->createPrivateAttachment($owner, $identifier->uuid);

        $this->actingAs(User::factory()->create())
            ->get($this->proxyUrl($identifier))
            ->assertForbidden();
    }

    public function testRoomMemberCanDownloadGroupAttachment(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $room = $this->createRoom($author, $member);
        $identifier = $this->storeFile(StoredFileCategory::GROUP);
        $this->createGroupAttachment($room, $author, $identifier->uuid);

        $this->actingAs($member)
            ->get($this->proxyUrl($identifier))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, private');
    }

    public function testNonMemberCannotDownloadGroupAttachment(): void
    {
        $author = User::factory()->create();
        $room = $this->createRoom($author);
        $identifier = $this->storeFile(StoredFileCategory::GROUP);
        $this->createGroupAttachment($room, $author, $identifier->uuid);

        $this->actingAs(User::factory()->create())
            ->get($this->proxyUrl($identifier))
            ->assertForbidden();
    }

    public function testUnknownAndExistingForeignFilesAreIndistinguishable(): void
    {
        $owner = User::factory()->create();
        $identifier = $this->storeFile(StoredFileCategory::PRIVATE);
        $this->createPrivateAttachment($owner, $identifier->uuid);
        $unknown = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, (string)Str::uuid());

        $attacker = User::factory()->create();
        $this->actingAs($attacker)->get($this->proxyUrl($identifier))->assertForbidden();
        $this->actingAs($attacker)->get($this->proxyUrl($unknown))->assertForbidden();
    }

    // =========================================================================

    private function storeFile(StoredFileCategory $category): StoredFileIdentifier
    {
        $storedFile = $this->app->make(FileStorageService::class)
            ->store(FileReference::fromContent('secret.txt', 'top secret'), $category);
        self::assertNotNull($storedFile);

        return StoredFileIdentifier::fromCategoryAndUuid($category, $storedFile->getUuid());
    }

    private function proxyUrl(StoredFileIdentifier $identifier): string
    {
        return '/api/hawki/v1/proxy/storage/' . $identifier;
    }
}
