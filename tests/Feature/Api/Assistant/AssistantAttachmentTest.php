<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Attachment;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\FileType;
use App\Services\Storage\Values\StoredFile;
use App\Services\Storage\Values\StoredFileCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * Covers the assistant knowledge-file attachment endpoints (upload + delete).
 *
 * Authorization and the `required` rule are asserted deterministically. The
 * happy-path upload is exercised by stubbing {@see FileStorageService} so its
 * advertised MIME-types list becomes `['application/pdf']`. The production rule
 * uses Laravel's `mimetypes:` validator (which checks the file's detected
 * content type), so `mimetypes:application/pdf` matches `doc.pdf`.
 */
#[CoversNothing()]
class AssistantAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function testNonCreatorIsForbiddenFromUploadingAttachment(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $other = User::factory()->create();
        $this->actingAsUser($other);

        $this->post(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment",
            ['file' => UploadedFile::fake()->create('doc.pdf', 10)],
            ['Accept' => 'application/vnd.api+json'],
        )->assertForbidden();
    }

    public function testGuestCannotUploadAttachment(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->post(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment",
            ['file' => UploadedFile::fake()->create('doc.pdf', 10)],
            ['Accept' => 'application/vnd.api+json'],
        )->assertForbidden();
    }

    public function testUploadAttachmentRequiresFile(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->actingAsUser($owner);

        $this->post(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment",
            [],
            ['Accept' => 'application/vnd.api+json'],
        )->assertStatus(422);
    }

    public function testCreatorCanDeleteOwnAttachment(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);
        $attachment = $assistant->attachments()->create([
            'uuid' => 'test-delete-' . uniqid(),
            'name' => 'knowledge.pdf',
            'category' => 'assistant',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $owner->id,
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment", [
            'fileId' => $attachment->uuid,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function testNonCreatorIsForbiddenFromDeletingAttachment(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);
        $attachment = $assistant->attachments()->create([
            'uuid' => 'test-forbidden-' . uniqid(),
            'name' => 'knowledge.pdf',
            'category' => 'assistant',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $owner->id,
        ]);

        $other = User::factory()->create();
        $this->actingAsUser($other);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment", [
            'fileId' => $attachment->uuid,
        ])->assertForbidden();
    }

    public function testGuestCannotDeleteAttachment(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment", [
            'fileId' => 'does-not-exist',
        ])->assertForbidden();
    }

    public function testDeleteAttachmentRequiresFileId(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment")
            ->assertStatus(422);
    }

    public function testDeleteAttachmentWithUnknownFileIdReturns404(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => 'organizational',
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment", [
            'fileId' => 'unknown-uuid',
        ])->assertNotFound();
    }

    public function testUploadAttachmentRecordsVersionWhenOrganizational(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->stubFileStorageForUpload();
        $this->actingAsUser($owner);

        $this->post(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment",
            ['file' => UploadedFile::fake()->create('doc.pdf', 10)],
            ['Accept' => 'application/vnd.api+json'],
        )->assertSuccessful();

        // The version listener merges into the factory-seeded initial version
        // (debounce window), so the row count stays put but the latest row's
        // changed_keys / text reflect this change.
        $assistant->refresh();
        self::assertSame($initialVersionCount, $assistant->assistantVersions()->count());

        $version = $assistant->assistantVersions()->latest('version')->first();
        self::assertSame('{"changes":["attachments"]}', $version->text);
        self::assertEquals(['attachments'], $version->changed_keys);
    }

    public function testUploadAttachmentSkipsVersionWhenDraft(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->stubFileStorageForUpload();
        $this->actingAsUser($owner);

        $this->post(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment",
            ['file' => UploadedFile::fake()->create('doc.pdf', 10)],
            ['Accept' => 'application/vnd.api+json'],
        )->assertSuccessful();

        // No event is dispatched for draft assistants, so the factory-seeded
        // version row is untouched.
        self::assertSame($initialVersionCount, $assistant->refresh()->assistantVersions()->count());

        $version = $assistant->assistantVersions()->latest('version')->first();
        self::assertSame('{"changes":[]}', $version->text);
    }

    public function testDeleteAttachmentRecordsVersionWhenOrganizational(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $attachment = $assistant->attachments()->create([
            'uuid' => 'test-delete-version-' . uniqid(),
            'name' => 'knowledge.pdf',
            'category' => 'assistant',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $owner->id,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment", [
            'fileId' => $attachment->uuid,
        ])->assertSuccessful();

        // The version listener merges into the factory-seeded initial version
        // (debounce window), so the row count stays put but the latest row's
        // changed_keys / text reflect this change.
        $assistant->refresh();
        self::assertSame($initialVersionCount, $assistant->assistantVersions()->count());

        $version = $assistant->assistantVersions()->latest('version')->first();
        self::assertSame('{"changes":["attachments"]}', $version->text);
        self::assertEquals(['attachments'], $version->changed_keys);
    }

    public function testDeleteAttachmentSkipsVersionWhenDraft(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::DRAFT->value,
        ]);
        $attachment = $assistant->attachments()->create([
            'uuid' => 'test-delete-skip-' . uniqid(),
            'name' => 'knowledge.pdf',
            'category' => 'assistant',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $owner->id,
        ]);
        $initialVersionCount = $assistant->assistantVersions()->count();

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment", [
            'fileId' => $attachment->uuid,
        ])->assertSuccessful();

        // No event is dispatched for draft assistants, so the factory-seeded
        // version row is untouched.
        self::assertSame($initialVersionCount, $assistant->refresh()->assistantVersions()->count());

        $version = $assistant->assistantVersions()->latest('version')->first();
        self::assertSame('{"changes":[]}', $version->text);
    }

    public function testUploadAttachmentResponseReflectsFavoritedState(): void
    {
        // The upload attachment action response must carry the per-user
        // is_favorite flag. The schema's loaderFor hook is what populates it
        // on this path; this test pins that the controller routes the
        // response through the store's queryOne rather than DataResponse::make
        // on a bare model.
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $owner->favoriteAssistants()->attach($assistant->id);

        $this->stubFileStorageForUpload();
        $this->actingAsUser($owner);

        $this->post(
            "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment",
            ['file' => UploadedFile::fake()->create('doc.pdf', 10)],
            ['Accept' => 'application/vnd.api+json'],
        )
            ->assertSuccessful()
            ->assertJsonPath('data.attributes.is_favorite', true);
    }

    public function testDeleteAttachmentResponseReflectsFavoritedState(): void
    {
        // The delete attachment action response must carry the per-user
        // is_favorite flag. The schema's loaderFor hook is what populates it
        // on this path; this test pins that the controller routes the
        // response through the store's queryOne rather than DataResponse::make
        // on a bare model.
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'creator_id' => $owner->id,
            'release_stage' => AssistantReleaseStage::ORGANIZATIONAL->value,
        ]);
        $owner->favoriteAssistants()->attach($assistant->id);
        $attachment = $assistant->attachments()->create([
            'uuid' => 'test-fav-state-' . uniqid(),
            'name' => 'knowledge.pdf',
            'category' => 'assistant',
            'type' => 'document',
            'mime' => 'application/pdf',
            'user_id' => $owner->id,
        ]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('delete', "/api/hawki/v1/assistants/{$assistant->id}/actions/attachment", [
            'fileId' => $attachment->uuid,
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.attributes.is_favorite', true);
    }

    /**
     * Short-circuit the on-disk storage pipeline by returning a stubbed
     * {@see StoredFile}, and advertise a real MIME type so the production
     * `mimetypes:` rule matches the fake upload. `AttachmentRepository` is
     * left un-mocked so a real Attachment row is persisted — the full
     * upload→attach→dispatch path is exercised.
     */
    private function stubFileStorageForUpload(): void
    {
        $storedFile = self::createStub(StoredFile::class);
        $storedFile->method('getUuid')->willReturn('test-upload-' . uniqid());
        $storedFile->method('getOriginalFilename')->willReturn('doc.pdf');
        $storedFile->method('getCategory')->willReturn(StoredFileCategory::ASSISTANT);
        $storedFile->method('getMimeType')->willReturn('application/pdf');
        $storedFile->method('getFileType')->willReturn(FileType::PDF);

        $this->mock(FileStorageService::class, static function ($mock) use ($storedFile): void {
            $mock->shouldReceive('getAllowedMimeTypes')->andReturn(['application/pdf']);
            $mock->shouldReceive('getMaxFileSize')->andReturn(10 * 1024 * 1024);
            $mock->shouldReceive('store')->andReturn($storedFile);
        });
    }
}
