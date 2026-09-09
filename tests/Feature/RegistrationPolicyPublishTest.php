<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcements\Announcement;
use App\Services\Announcements\AnnouncementService;
use App\Services\Announcements\Exceptions\OverlappingPolicyException;
use App\Services\Announcements\PolicyContentHasher;
use App\Services\Announcements\RegistrationPolicyService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
class RegistrationPolicyPublishTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('session.driver', 'array');
        DB::purge('sqlite');
        (require database_path('migrations/2025_08_21_175642_create_announcements.php'))->up();
    }

    public function testAnnouncementPublishingRejectsOverlappingGlobalPolicies(): void
    {
        $announcements = $this->app->make(AnnouncementService::class);
        $first = $announcements->createAnnouncement(
            'First policy',
            'basic-guidelines',
            'policy',
            startsAt: '2026-01-01 00:00:00',
            expiresAt: '2026-06-30 23:59:59'
        );

        try {
            $announcements->createAnnouncement(
                'Overlapping policy',
                'basic-guidelines',
                'policy',
                startsAt: '2026-06-01 00:00:00',
                expiresAt: '2026-12-31 23:59:59'
            );
            self::fail('The overlapping policy should have been rejected.');
        } catch (OverlappingPolicyException) {
            self::assertSame([$first->id], Announcement::query()->pluck('id')->all());
        }
    }

    public function testNonOverlappingPolicyIsPublishedThroughTheActualAnnouncementFlow(): void
    {
        $announcements = $this->app->make(AnnouncementService::class);
        $announcements->createAnnouncement(
            'Old policy',
            'basic-guidelines',
            'policy',
            startsAt: '2026-01-01 00:00:00',
            expiresAt: '2026-06-30 23:59:59'
        );
        $next = $announcements->createAnnouncement(
            'New policy',
            'basic-guidelines',
            'policy',
            startsAt: '2026-07-01 00:00:00'
        );

        self::assertSame('policy', $next->type);
        self::assertTrue($next->is_global);
        self::assertSame(2, Announcement::query()->count());
    }

    public function testResolverUsesTheLatestActivePolicyAndHashesNormalizedRequestedLocaleText(): void
    {
        DB::table('announcements')->insert([
            [
                'title' => 'Older', 'view' => 'first-upload', 'type' => 'policy',
                'is_forced' => false, 'is_global' => true, 'target_users' => null, 'anchor' => null,
                'starts_at' => null, 'expires_at' => null,
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
            ],
            [
                'title' => 'Current', 'view' => 'basic-guidelines', 'type' => 'policy',
                'is_forced' => false, 'is_global' => true, 'target_users' => null, 'anchor' => null,
                'starts_at' => null, 'expires_at' => null,
                'created_at' => '2026-02-01 00:00:00', 'updated_at' => '2026-02-01 00:00:00',
            ],
        ]);

        $policy = $this->app->make(RegistrationPolicyService::class)->resolve('de_DE');

        self::assertSame(2, $policy->id);
        self::assertSame('de_DE', $policy->locale);
        self::assertStringNotContainsString("\r", $policy->text);
        self::assertDoesNotMatchRegularExpression('/[ \t]+$/m', $policy->text);
        self::assertSame($this->app->make(PolicyContentHasher::class)->hash($policy->text), $policy->hash);
    }
}
