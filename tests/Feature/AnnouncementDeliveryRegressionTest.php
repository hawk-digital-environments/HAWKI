<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcements\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AnnouncementDeliveryRegressionTest extends TestCase
{
    use DatabaseTransactions;

    public function testLegacyRendererUsesDatabaseContentCreatedByAdministration(): void
    {
        $user = User::factory()->create();
        $announcement = $this->announcement([
            'view' => 'admin',
            'content' => ['en_US' => '# Database body', 'de_DE' => '# Database body'],
        ]);

        $this->actingAs($user)
            ->getJson("/req/announcement/render/{$announcement->id}")
            ->assertOk()
            ->assertJsonPath('view', '# Database body');
    }

    public function testLegacyAndJsonActionsRejectAnnouncementsOutsideTheActiveAudience(): void
    {
        $this->withSession(['_token' => 'announcement-test-token']);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $announcements = [
            $this->announcement(['is_published' => false]),
            $this->announcement(['starts_at' => now()->addDay()]),
            $this->announcement(['expires_at' => now()->subDay()]),
            $this->announcement(['is_global' => false, 'target_users' => [$otherUser->id]]),
        ];

        foreach ($announcements as $announcement) {
            $this->actingAs($user)->getJson("/req/announcement/render/{$announcement->id}")->assertNotFound();
            $this->actingAs($user)->postJson("/req/announcement/seen/{$announcement->id}", [
                '_token' => 'announcement-test-token',
            ])->assertStatus(400);
            $this->actingAs($user)->postJson("/req/announcement/report/{$announcement->id}", [
                '_token' => 'announcement-test-token',
            ])->assertStatus(400);
            $this->actingAs($user)->postJson('/api/hawki/v1/announcements/actions/seen', [
                'announcement_id' => $announcement->id,
            ], $this->jsonApiHeaders())->assertNotFound();
            $this->actingAs($user)->postJson('/api/hawki/v1/announcements/actions/accept', [
                'announcement_id' => $announcement->id,
            ], $this->jsonApiHeaders())->assertNotFound();
            self::assertDatabaseMissing('announcement_user', [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
            ]);
        }
    }

    public function testNonAdminAnnouncementCreationUsesPublicationRules(): void
    {
        $this->expectException(ValidationException::class);

        $this->app->make(\App\Services\Announcements\AnnouncementService::class)->createAnnouncement(
            'Missing content',
            'missing-announcement-content',
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function announcement(array $overrides = []): Announcement
    {
        return Announcement::query()->create(array_merge([
            'title' => 'Announcement',
            'view' => 'basic-guidelines',
            'type' => 'info',
            'is_forced' => false,
            'is_global' => true,
            'target_users' => null,
            'target_users' => [],
            'content' => null,
            'is_published' => true,
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
