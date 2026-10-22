<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\AnnouncementPublish;
use App\Models\Announcements\Announcement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Feature test of {@see AnnouncementPublish}: flag parsing, boolean coercion,
 * user-id normalization and the anchor string, verified through the created
 * database row (the service is exercised for real; rows roll back).
 *
 * Note: the service is deliberately not mocked — `AnnouncementService` is a
 * readonly class, which Mockery cannot subclass.
 */
#[CoversClass(AnnouncementPublish::class)]
class AnnouncementPublishTest extends TestCase
{
    use DatabaseTransactions;

    public function testItCreatesAnAnnouncementFromFlags(): void
    {
        $this->artisan(
            'announcement:publish',
            [
                'title' => 'Publish Command Sample',
                'view' => 'publish-command-sample',
                '--type' => 'system',
                '--force' => 'true',
                '--global' => 'false',
                '--users' => ['3', '7'],
                '--anchor' => 'FileUpload',
                '--start' => '2026-10-01 08:00:00',
                '--expire' => '2027-10-01 08:00:00',
                '--no-interaction' => true,
            ],
        )->assertExitCode(0);

        $announcement = Announcement::query()->where('view', 'publish-command-sample')->firstOrFail();

        self::assertSame('Publish Command Sample', $announcement->title);
        self::assertSame('system', $announcement->type);
        self::assertTrue((bool) $announcement->is_forced);
        self::assertFalse((bool) $announcement->is_global);
        self::assertSame([3, 7], $announcement->target_users);
        // The anchor is a frontend event name and must survive as a string.
        self::assertSame('FileUpload', $announcement->anchor);
        self::assertSame('2026-10-01 08:00:00', $announcement->starts_at->format('Y-m-d H:i:s'));
        self::assertSame('2027-10-01 08:00:00', $announcement->expires_at->format('Y-m-d H:i:s'));
    }

    public function testItNormalizesRepeatedAndCommaJoinedUserOptions(): void
    {
        $this->artisan(
            'announcement:publish',
            [
                'title' => 'Exact Args',
                'view' => 'exact-args',
                '--type' => 'news',
                '--force' => 'true',
                '--global' => 'false',
                // Repeated and comma-joined options mix freely.
                '--users' => ['1', '2, 3'],
                '--anchor' => 'FileUpload',
                '--start' => '2026-10-01 08:00:00',
                '--no-interaction' => true,
            ],
        )->assertExitCode(0);

        $announcement = Announcement::query()->where('view', 'exact-args')->firstOrFail();

        self::assertSame([1, 2, 3], $announcement->target_users);
        self::assertSame('FileUpload', $announcement->anchor);
    }

    public function testItCoercesBooleanStringFlags(): void
    {
        $this->artisan(
            'announcement:publish',
            [
                'title' => 'Coerced Flags',
                'view' => 'coerced-flags',
                '--type' => 'info',
                '--force' => 'false',
                '--global' => 'true',
                '--start' => '2026-10-01 08:00:00',
                '--no-interaction' => true,
            ],
        )->assertExitCode(0);

        $announcement = Announcement::query()->where('view', 'coerced-flags')->firstOrFail();

        self::assertFalse((bool) $announcement->is_forced);
        self::assertTrue((bool) $announcement->is_global);
        // Global announcements never carry target users.
        self::assertNull($announcement->target_users);
        self::assertNull($announcement->anchor);
    }

    public function testItDropsEmptyUserIds(): void
    {
        $this->artisan(
            'announcement:publish',
            [
                'title' => 'Clean Users',
                'view' => 'clean-users',
                '--type' => 'info',
                '--force' => 'false',
                '--global' => 'false',
                '--users' => ['4', '', ' 5 , '],
                '--start' => '2026-10-01 08:00:00',
                '--no-interaction' => true,
            ],
        )->assertExitCode(0);

        $announcement = Announcement::query()->where('view', 'clean-users')->firstOrFail();

        self::assertSame([4, 5], $announcement->target_users);
    }
}
