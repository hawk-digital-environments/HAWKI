<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\User;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgentFromLegacyRequestFactory;
use App\Services\Ai\Agents\Implementations\Chat\ChatToolResolver;
use App\Services\Ai\Models\Identifiers\ModelFinder;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Chat\Attachment\AttachmentAccessGuard;
use App\Services\Chat\Room\Repositories\RoomRepository;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\System\UsageTypes\UsageContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Ai\Providers\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * Regression tests for the attachment IDOR on /req/streamAI: a client-supplied attachment UUID must
 * only reach the AI model when the current user may access the file. Every resolved file goes
 * through FileStorageService::retrieve(), so the recorded calls show which files would be sent.
 */
#[CoversClass(ChatAgentFromLegacyRequestFactory::class)]
class ChatAgentFromLegacyRequestFactoryAttachmentAccessTest extends TestCase
{
    use DatabaseTransactions;
    use ChatAttachmentFixtures;

    /** @var list<string> */
    private array $retrievedFiles = [];

    // =========================================================================
    // Private chat requests
    // =========================================================================

    public function testOwnPrivateAttachmentIsResolved(): void
    {
        $owner = User::factory()->create();
        $uuid = $this->createPrivateAttachment($owner);

        $this->actingAs($owner);
        $this->createAgent($this->privateRequest($uuid));

        self::assertSame([(string)$this->privateId($uuid)], $this->retrievedFiles);
    }

    public function testForeignPrivateAttachmentIsNeverResolved(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();
        $uuid = $this->createPrivateAttachment($victim);

        $this->actingAs($attacker);
        $this->createAgent($this->privateRequest($uuid));

        self::assertSame([], $this->retrievedFiles);
    }

    public function testOnlyTheAccessibleAttachmentsOfAMixedRequestAreResolved(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();
        $foreignUuid = $this->createPrivateAttachment($victim);
        $ownUuid = $this->createPrivateAttachment($attacker);

        $this->actingAs($attacker);
        $this->createAgent($this->privateRequest($foreignUuid, $ownUuid));

        self::assertSame([(string)$this->privateId($ownUuid)], $this->retrievedFiles);
    }

    public function testNothingIsResolvedWithoutAnAuthenticatedUser(): void
    {
        $owner = User::factory()->create();
        $uuid = $this->createPrivateAttachment($owner);

        $this->createAgent($this->privateRequest($uuid));

        self::assertSame([], $this->retrievedFiles);
    }

    public function testNonStringAttachmentReferencesAreIgnored(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->createAgent($this->privateRequest(['nested' => 'value'], 42));

        self::assertSame([], $this->retrievedFiles);
    }

    // =========================================================================
    // Group chat requests
    // =========================================================================

    public function testGroupAttachmentOfTheRequestedRoomIsResolved(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $room = $this->createRoom($author, $member);
        $uuid = $this->createGroupAttachment($room, $author);

        $this->actingAs($member);
        $this->createAgent($this->groupRequest($room->slug, $uuid));

        self::assertSame([(string)$this->groupId($uuid)], $this->retrievedFiles);
    }

    public function testGroupAttachmentOfAnotherRoomIsNeverResolved(): void
    {
        $user = User::factory()->create();
        $requestedRoom = $this->createRoom($user);
        $otherRoom = $this->createRoom($user);
        $uuid = $this->createGroupAttachment($otherRoom, $user);

        $this->actingAs($user);
        $this->createAgent($this->groupRequest($requestedRoom->slug, $uuid));

        self::assertSame([], $this->retrievedFiles);
    }

    public function testFormerRoomMemberCannotResolveGroupAttachments(): void
    {
        $author = User::factory()->create();
        $formerMember = User::factory()->create();
        $room = $this->createRoom($author);
        $this->addMember($room, $formerMember, isRemoved: true);
        $uuid = $this->createGroupAttachment($room, $author);

        $this->actingAs($formerMember);
        $this->createAgent($this->groupRequest($room->slug, $uuid));

        self::assertSame([], $this->retrievedFiles);
    }

    public function testGroupAttachmentIsNeverResolvedWithoutARoomSlug(): void
    {
        $user = User::factory()->create();
        $room = $this->createRoom($user);
        $uuid = $this->createGroupAttachment($room, $user);

        $this->actingAs($user);
        $this->createAgent($this->groupRequest(null, $uuid));

        self::assertSame([], $this->retrievedFiles);
    }

    public function testPrivateAttachmentCannotBeSmuggledIntoAGroupRequest(): void
    {
        $user = User::factory()->create();
        $room = $this->createRoom($user);
        $uuid = $this->createPrivateAttachment($user);

        $this->actingAs($user);
        $this->createAgent($this->groupRequest($room->slug, $uuid));

        self::assertSame([], $this->retrievedFiles);
    }

    // =========================================================================

    private function createAgent(array $request): void
    {
        $fileStorage = $this->createMock(FileStorageService::class);
        $fileStorage->method('retrieve')->willReturnCallback(
            function (StoredFileIdentifier|null $identifier) {
                $this->retrievedFiles[] = (string)$identifier;
                return null;
            }
        );

        $modelFinder = $this->createMock(ModelFinder::class);
        $modelFinder->method('findOrFail')->willReturn(new AiModel());

        $toolResolver = $this->createMock(ChatToolResolver::class);
        $toolResolver->method('findTools')->willReturn([]);

        $providerProxyResolver = $this->createMock(AiProviderProxyResolver::class);
        $providerProxyResolver->method('resolveForModel')->willReturn(new AiProviderProxy(
            provider: new AiProvider(),
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $this->createMock(Provider::class),
        ));

        $usageContext = $this->createMock(UsageContext::class);
        $usageContext->method('getForGiven')->willReturn('main_app');

        $factory = new ChatAgentFromLegacyRequestFactory(
            fileStorageService: $fileStorage,
            modelFinder: $modelFinder,
            toolResolver: $toolResolver,
            logger: new NullLogger(),
            attachmentAccessGuard: $this->app->make(AttachmentAccessGuard::class),
            roomRepository: $this->app->make(RoomRepository::class),
            auth: $this->app->make('auth'),
        );
        $factory->setProviderProxyResolver($providerProxyResolver);
        $factory->setUsageContext($usageContext);

        self::assertNotNull($factory->createAgent($request));
    }

    private function privateRequest(mixed ...$attachments): array
    {
        return ['payload' => $this->payload($attachments, broadcast: false)];
    }

    private function groupRequest(string|null $slug, mixed ...$attachments): array
    {
        return [
            'slug' => $slug,
            'payload' => $this->payload($attachments, broadcast: true),
        ];
    }

    private function payload(array $attachments, bool $broadcast): array
    {
        return [
            'model' => 'test/model',
            'broadcast' => $broadcast,
            'messages' => [
                ['role' => 'system', 'content' => ['text' => 'You are a test.']],
                ['role' => 'user', 'content' => ['text' => 'Describe the image.', 'attachments' => $attachments]],
            ],
        ];
    }
}
