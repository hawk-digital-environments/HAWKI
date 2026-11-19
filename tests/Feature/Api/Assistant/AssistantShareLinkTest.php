<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantShareLink;
use App\Models\User;
use App\Services\System\Time\CarbonClockInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AssistantShareLinkTest extends TestCase
{
    use RefreshDatabase;

    private const REDEEM_URI = '/api/hawki/v1/assistants/actions/redeem-share-link';

    // --- actions/share-link: create ---

    public function testCreatorCanCreateShareLink(): void
    {
        config(['assistant.share_links.ttl_hours' => 24]);
        $at = $this->freezeClock();

        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'draft']);

        $this->actingAsUser($owner);

        $response = $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share-link", [
            'data' => ['can_edit' => true],
        ])
            ->assertOk()
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.expires_at', $at->addHours(24)->toIso8601String());

        $token = $response->json('data.token');
        self::assertIsString($token);

        $link = AssistantShareLink::query()->sole();
        self::assertSame($assistant->id, $link->assistant_id);
        self::assertSame(hash('sha256', $token), $link->token_hash);
        self::assertTrue($link->can_edit);
    }

    public function testEveryCreateMintsADistinctToken(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $uri = "/api/hawki/v1/assistants/{$assistant->id}/actions/share-link";
        $first = $this->jsonApiRaw('post', $uri, ['data' => []])->assertOk()->json('data.token');
        $second = $this->jsonApiRaw('post', $uri, ['data' => []])->assertOk()->json('data.token');

        self::assertNotSame($first, $second);
        self::assertSame(2, AssistantShareLink::query()->count());
    }

    public function testTtlIsReadFromConfig(): void
    {
        config(['assistant.share_links.ttl_hours' => 2]);
        $this->app->forgetInstance(\App\Services\Assistant\Config\AssistantConfig::class);
        $at = $this->freezeClock();

        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $this->actingAsUser($owner);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share-link", ['data' => []])
            ->assertOk()
            ->assertJsonPath('data.expires_at', $at->addHours(2)->toIso8601String());
    }

    public function testNonCreatorCannotCreateShareLink(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        $editor = User::factory()->create();
        $assistant->sharedUsers()->sync([$editor->id => ['can_edit' => true]]);

        $this->actingAsUser($editor);

        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share-link", ['data' => []])
            ->assertForbidden();

        self::assertSame(0, AssistantShareLink::query()->count());
    }

    // --- actions/redeem-share-link ---

    public function testRedeemSharesDraftAssistantAndAnswersWithIt(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'draft']);
        $token = $this->createLink($owner, $assistant, canEdit: false);

        $guest = User::factory()->create();
        $this->actingAsUser($guest);

        $this->jsonApiRaw('post', self::REDEEM_URI, ['data' => ['token' => $token]])
            ->assertOk()
            ->assertJsonPath('data.id', (string) $assistant->id);

        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $guest->id,
            'can_edit' => false,
        ]);

        $this->jsonApiRaw('get', "/api/hawki/v1/assistants/{$assistant->id}")->assertOk();
    }

    public function testEditLinkGrantsEditRights(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id, 'release_stage' => 'private']);
        $token = $this->createLink($owner, $assistant, canEdit: true);

        $guest = User::factory()->create();
        $this->actingAsUser($guest);

        $this->jsonApiRaw('post', self::REDEEM_URI, ['data' => ['token' => $token]])->assertOk();

        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $guest->id,
            'can_edit' => true,
        ]);
    }

    public function testEditLinkUpgradesViewOnlySharee(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $viewer = User::factory()->create();
        $assistant->sharedUsers()->sync([$viewer->id => ['can_edit' => false]]);
        $token = $this->createLink($owner, $assistant, canEdit: true);

        $this->actingAsUser($viewer);
        $this->jsonApiRaw('post', self::REDEEM_URI, ['data' => ['token' => $token]])->assertOk();

        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $viewer->id,
            'can_edit' => true,
        ]);
    }

    public function testViewLinkNeverDowngradesEditor(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $editor = User::factory()->create();
        $assistant->sharedUsers()->sync([$editor->id => ['can_edit' => true]]);
        $token = $this->createLink($owner, $assistant, canEdit: false);

        $this->actingAsUser($editor);
        $this->jsonApiRaw('post', self::REDEEM_URI, ['data' => ['token' => $token]])->assertOk();

        $this->assertDatabaseHas('assistant_shared_users', [
            'assistant_id' => $assistant->id,
            'user_id' => $editor->id,
            'can_edit' => true,
        ]);
    }

    public function testCreatorRedeemingOwnLinkIsNoOp(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $token = $this->createLink($owner, $assistant, canEdit: true);

        $this->actingAsUser($owner);
        $this->jsonApiRaw('post', self::REDEEM_URI, ['data' => ['token' => $token]])
            ->assertOk()
            ->assertJsonPath('data.id', (string) $assistant->id);

        $this->assertDatabaseMissing('assistant_shared_users', ['assistant_id' => $assistant->id]);
    }

    public function testLinkIsMultiUse(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $token = $this->createLink($owner, $assistant, canEdit: false);

        foreach (User::factory()->count(2)->create() as $guest) {
            $this->actingAsUser($guest);
            $this->jsonApiRaw('post', self::REDEEM_URI, ['data' => ['token' => $token]])->assertOk();
        }

        self::assertSame(2, $assistant->sharedUsers()->count());
    }

    public function testExpiredLinkIsRejected(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $token = $this->createLink($owner, $assistant, canEdit: false);

        AssistantShareLink::query()->update(['expires_at' => now()->subHour()]);

        $this->actingAsUser(User::factory()->create());
        $this->jsonApiRaw('post', self::REDEEM_URI, ['data' => ['token' => $token]])
            ->assertForbidden();

        $this->assertDatabaseMissing('assistant_shared_users', ['assistant_id' => $assistant->id]);
    }

    public function testUnknownTokenIsRejected(): void
    {
        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('post', self::REDEEM_URI, ['data' => ['token' => 'not-a-real-token']])
            ->assertForbidden();
    }

    public function testMissingTokenFailsValidation(): void
    {
        $this->actingAsUser(User::factory()->create());

        $this->jsonApiRaw('post', self::REDEEM_URI, ['data' => []])
            ->assertUnprocessable();
    }

    public function testSystemUserCannotRedeem(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $token = $this->createLink($owner, $assistant, canEdit: false);

        $this->actingAsUser(User::factory()->create(['employeetype' => 'system']));
        $this->jsonApiRaw('post', self::REDEEM_URI, ['data' => ['token' => $token]])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('assistant_shared_users', ['assistant_id' => $assistant->id]);
    }

    public function testSyncStillDetachesLinkJoinedUsers(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);
        $token = $this->createLink($owner, $assistant, canEdit: false);

        $this->actingAsUser(User::factory()->create());
        $this->jsonApiRaw('post', self::REDEEM_URI, ['data' => ['token' => $token]])->assertOk();

        $this->actingAsUser($owner);
        $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share", ['data' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testExpiredLinksArePruned(): void
    {
        $owner = User::factory()->create();
        $assistant = Assistant::factory()->create(['creator_id' => $owner->id]);

        // The model's prunable() reads the real wall clock: minted 25h ago,
        // the first link is past its 24h TTL; the second stays inside it.
        $now = CarbonImmutable::now('UTC');
        $this->freezeClock($now->subHours(25));
        $this->createLink($owner, $assistant, canEdit: false);

        $this->advanceClock($now);
        $this->createLink($owner, $assistant, canEdit: false);

        $this->artisan('model:prune', ['--model' => [AssistantShareLink::class]])->assertSuccessful();

        self::assertSame(1, AssistantShareLink::query()->count());
    }

    private function createLink(User $owner, Assistant $assistant, bool $canEdit): string
    {
        $this->actingAsUser($owner);

        return $this->jsonApiRaw('post', "/api/hawki/v1/assistants/{$assistant->id}/actions/share-link", [
            'data' => ['can_edit' => $canEdit],
        ])->assertOk()->json('data.token');
    }

    /**
     * The mock clock must be installed while the application is created:
     * route registration resolves the assistant schema and its repository
     * — and with them the clock — during boot, before any test method runs.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        // Real time unless a test freezes it; pinned once so unfrozen tests
        // never see the clock jump mid-test.
        $this->clockNow ??= CarbonImmutable::now('UTC');

        $clock = $this->createMock(CarbonClockInterface::class);
        $clock->method('now')->willReturnCallback(fn (): CarbonImmutable => $this->clockNow ?? throw new \LogicException('Clock not frozen'));
        $this->app->instance(CarbonClockInterface::class, $clock);
    }

    /**
     * Freezes the mocked application clock — Laravel's freezeTime() only
     * mocks Carbon and never reaches the injected clock. Advance it via
     * {@see advanceClock()}; every service holding the mock observes it.
     */
    private function freezeClock(?CarbonImmutable $at = null): CarbonImmutable
    {
        return $this->clockNow = $at ?? new CarbonImmutable('2026-11-19 12:00:00', 'UTC');
    }

    private function advanceClock(CarbonImmutable $to): void
    {
        $this->clockNow = $to;
    }

    private ?CarbonImmutable $clockNow = null;
}
