<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcements\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AnnouncementsApiTest extends TestCase
{
    use DatabaseTransactions;

    public function testItListsGlobalAnnouncementsWithContentAndPivotStateForTheCurrentUser(): void
    {
        $user = User::factory()->create();
        $announcement = $this->createAnnouncement();
        $user->markAnnouncementAsSeen($announcement->id);

        $response = $this->actingAs($user)
            ->getJson('/api/hawki/v1/announcements', $this->jsonApiHeaders())
            ->assertOk();

        $item = collect($response->json('data'))
            ->firstWhere('id', (string) $announcement->id);

        self::assertNotNull($item);
        self::assertSame('policy', $item['attributes']['type']);
        self::assertTrue($item['attributes']['is_global']);
        self::assertTrue($item['attributes']['is_forced']);
        self::assertTrue($item['attributes']['is_active']);
        self::assertNotSame('', $item['attributes']['content']);
        self::assertNotNull($item['attributes']['seen_at']);
        self::assertNull($item['attributes']['accepted_at']);
        self::assertSame(1, $item['attributes']['seen_count']);
    }

    public function testItHidesAnnouncementsTargetedAtOtherUsersAndNotYetStartedOnes(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $targetedAtOther = $this->createAnnouncement([
            'is_global' => false,
            'target_users' => [$otherUser->id],
        ]);
        $notStarted = $this->createAnnouncement([
            'starts_at' => now()->addDay(),
        ]);

        $ids = collect(
            $this->actingAs($user)
                ->getJson('/api/hawki/v1/announcements', $this->jsonApiHeaders())
                ->assertOk()
                ->json('data'),
        )->pluck('id');

        self::assertNotContains((string) $targetedAtOther->id, $ids);
        self::assertNotContains((string) $notStarted->id, $ids);
    }

    public function testItListsAnnouncementsTargetedAtTheCurrentUser(): void
    {
        $user = User::factory()->create();
        // Int ids — string ids would not match the JSON visibility query on MySQL.
        $targeted = $this->createAnnouncement([
            'is_global' => false,
            'target_users' => [$user->id],
        ]);

        $ids = collect(
            $this->actingAs($user)
                ->getJson('/api/hawki/v1/announcements', $this->jsonApiHeaders())
                ->assertOk()
                ->json('data'),
        )->pluck('id');

        self::assertContains((string) $targeted->id, $ids);
    }

    public function testItStillListsExpiredAnnouncementsAsInactiveForTheHistory(): void
    {
        $user = User::factory()->create();
        $expired = $this->createAnnouncement([
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);

        $item = collect(
            $this->actingAs($user)
                ->getJson('/api/hawki/v1/announcements', $this->jsonApiHeaders())
                ->assertOk()
                ->json('data'),
        )->firstWhere('id', (string) $expired->id);

        self::assertNotNull($item);
        self::assertFalse($item['attributes']['is_active']);
    }

    public function testItOrdersNewestFirstWithUndatedLastAndStableTies(): void
    {
        $user = User::factory()->create();

        $older = $this->createAnnouncement(['starts_at' => now()->subDays(3)]);
        // Both share the exact same `starts_at`; the later-created one wins the tie.
        $sharedStart = now()->subDay();
        $firstWithSharedStart = $this->createAnnouncement(['starts_at' => $sharedStart]);
        $secondWithSharedStart = $this->createAnnouncement(['starts_at' => $sharedStart]);
        $undated = $this->createAnnouncement(['starts_at' => null]);

        $ids = collect(
            $this->actingAs($user)
                ->getJson('/api/hawki/v1/announcements', $this->jsonApiHeaders())
                ->assertOk()
                ->json('data'),
        )->pluck('id');

        // Compare only the created rows — seeded announcements may be interleaved.
        self::assertSame(
            [
                (string) $secondWithSharedStart->id,
                (string) $firstWithSharedStart->id,
                (string) $older->id,
                (string) $undated->id,
            ],
            $ids->intersect([
                (string) $secondWithSharedStart->id,
                (string) $firstWithSharedStart->id,
                (string) $older->id,
                (string) $undated->id,
            ])->values()->all(),
        );
    }

    public function testItMarksAnAnnouncementAsSeenForTheCurrentUser(): void
    {
        $user = User::factory()->create();
        $announcement = $this->createAnnouncement();

        $this->actingAs($user)
            ->patchJson(
                "/api/hawki/v1/announcements/{$announcement->id}",
                $this->patchDocument($announcement, ['seen' => true]),
                $this->jsonApiHeaders(),
            )
            ->assertOk()
            ->assertJsonPath('data.attributes.accepted_at', null);

        $pivot = $announcement->users()->whereKey($user->id)->first()?->pivot;
        self::assertNotNull($pivot?->seen_at);
        self::assertNull($pivot->accepted_at);
    }

    public function testItMarksAnAnnouncementAsAcceptedForTheCurrentUser(): void
    {
        $user = User::factory()->create();
        $announcement = $this->createAnnouncement();

        $this->actingAs($user)
            ->patchJson(
                "/api/hawki/v1/announcements/{$announcement->id}",
                $this->patchDocument($announcement, ['accepted' => true]),
                $this->jsonApiHeaders(),
            )
            ->assertOk();

        $pivot = $announcement->users()->whereKey($user->id)->first()?->pivot;
        self::assertNotNull($pivot?->accepted_at);
    }

    public function testItAppliesSeenAndAcceptedInOnePatch(): void
    {
        $user = User::factory()->create();
        $announcement = $this->createAnnouncement();

        $this->actingAs($user)
            ->patchJson(
                "/api/hawki/v1/announcements/{$announcement->id}",
                $this->patchDocument($announcement, ['seen' => true, 'accepted' => true]),
                $this->jsonApiHeaders(),
            )
            ->assertOk()
            ->assertJsonPath('data.attributes.seen_at', static fn ($seenAt) => null !== $seenAt)
            ->assertJsonPath('data.attributes.accepted_at', static fn ($acceptedAt) => null !== $acceptedAt);
    }

    public function testItRejectsPatchingAnnouncementsInvisibleToTheCurrentUser(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $targetedAtOther = $this->createAnnouncement([
            'is_global' => false,
            'target_users' => [$otherUser->id],
        ]);

        $this->actingAs($user)
            ->patchJson(
                "/api/hawki/v1/announcements/{$targetedAtOther->id}",
                $this->patchDocument($targetedAtOther, ['accepted' => true]),
                $this->jsonApiHeaders(),
            )
            ->assertNotFound();

        self::assertDatabaseMissing('announcement_user', [
            'announcement_id' => $targetedAtOther->id,
            'user_id' => $user->id,
        ]);
    }

    public function testItRejectsPatchingWithoutSeenOrAccepted(): void
    {
        $user = User::factory()->create();
        $announcement = $this->createAnnouncement();

        // An empty attributes object is rejected by the JSON:API spec validation.
        $this->actingAs($user)
            ->patchJson(
                "/api/hawki/v1/announcements/{$announcement->id}",
                $this->patchDocument($announcement, []),
                $this->jsonApiHeaders(),
            )
            ->assertStatus(400);

        // Attributes without a transition flag are rejected by the request rules.
        $this->actingAs($user)
            ->patchJson(
                "/api/hawki/v1/announcements/{$announcement->id}",
                $this->patchDocument($announcement, ['title' => 'nope']),
                $this->jsonApiHeaders(),
            )
            ->assertStatus(422);

        self::assertDatabaseMissing('announcement_user', [
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
        ]);
    }

    public function testItRejectsNonBooleanTransitionFlags(): void
    {
        $user = User::factory()->create();
        $announcement = $this->createAnnouncement();

        $this->actingAs($user)
            ->patchJson(
                "/api/hawki/v1/announcements/{$announcement->id}",
                $this->patchDocument($announcement, ['seen' => 'yes']),
                $this->jsonApiHeaders(),
            )
            ->assertStatus(422);
    }

    public function testItRequiresAuthenticationForPatching(): void
    {
        $announcement = $this->createAnnouncement();

        $this->patchJson(
            "/api/hawki/v1/announcements/{$announcement->id}",
            $this->patchDocument($announcement, ['accepted' => true]),
            $this->jsonApiHeaders(),
        )->assertUnauthorized();
    }

    // =========================================================================

    /**
     * @param array<string, mixed> $attributes
     */
    private function patchDocument(Announcement $announcement, array $attributes): array
    {
        return [
            'data' => [
                'type' => 'announcements',
                'id' => (string) $announcement->id,
                'attributes' => $attributes,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createAnnouncement(array $overrides = []): Announcement
    {
        return Announcement::query()->create(array_merge([
            'title' => 'guidelines',
            // Reuses the shipped announcement content files so the content
            // resolution from resources/announcements/{view}/{lang}.md is covered.
            'view' => 'basic-guidelines',
            'type' => 'policy',
            'is_forced' => true,
            'is_global' => true,
            'target_users' => null,
            'anchor' => null,
            'starts_at' => now()->subDay(),
            'expires_at' => null,
        ], $overrides));
    }

    /**
     * @return array<string, string>
     */
    private function jsonApiHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.api+json,application/json',
            'Content-Type' => 'application/vnd.api+json',
        ];
    }
}
