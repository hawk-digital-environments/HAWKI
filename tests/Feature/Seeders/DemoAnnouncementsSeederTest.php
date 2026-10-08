<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Announcements\Announcement;
use App\Models\User;
use Database\Seeders\DemoAnnouncementsSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Feature test of {@see DemoAnnouncementsSeeder}: creates the six demo
 * announcements together with their markdown
 * content files, idempotently.
 */
#[CoversClass(DemoAnnouncementsSeeder::class)]
class DemoAnnouncementsSeederTest extends TestCase
{
    use DatabaseTransactions;
    private const array DEMO_VIEWS = [
        'maintenance-window',
        'new-usage-policy',
        'my-first-upload',
        'only-you',
        'coming-soon',
        'old-news',
    ];
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = $this->app->make(Filesystem::class);
    }

    protected function tearDown(): void
    {
        foreach (self::DEMO_VIEWS as $view) {
            $this->files->deleteDirectory(resource_path("announcements/{$view}"));
        }

        parent::tearDown();
    }

    public function testItSeedsAllSixDemoScenariosWithTheirContentFiles(): void
    {
        User::factory()->count(2)->create();

        $this->seed(DemoAnnouncementsSeeder::class);

        $byView = Announcement::query()
            ->whereIn('view', self::DEMO_VIEWS)
            ->get()
            ->keyBy('view');

        self::assertCount(6, $byView);

        // A) Global news — unforced dialog, news page.
        $news = $byView['maintenance-window'];
        self::assertSame('news', $news->type);
        self::assertFalse((bool) $news->is_forced);
        self::assertTrue((bool) $news->is_global);
        self::assertNull($news->anchor);

        // B) Forced policy.
        $policy = $byView['new-usage-policy'];
        self::assertSame('policy', $policy->type);
        self::assertTrue((bool) $policy->is_forced);

        // C) Anchored to the file-upload trigger.
        $anchored = $byView['my-first-upload'];
        self::assertSame('FileUpload', $anchored->anchor);

        // D) Targeted at exactly the two lowest user ids.
        $targeted = $byView['only-you'];
        self::assertFalse((bool) $targeted->is_global);
        self::assertSame(
            User::query()->orderBy('id')->limit(2)->pluck('id')->all(),
            $targeted->target_users,
        );

        // E) Scheduled in the future — stays invisible until then.
        self::assertTrue($byView['coming-soon']->starts_at->isFuture());

        // F) Expired — history only.
        self::assertTrue($byView['old-news']->expires_at->isPast());

        // Content files exist for both locales, with the expected shape.
        foreach (self::DEMO_VIEWS as $view) {
            foreach (['de_DE', 'en_US'] as $locale) {
                self::assertFileExists(resource_path("announcements/{$view}/{$locale}.md"));
            }
        }

        self::assertStringStartsWith(
            '# Planned Maintenance Window',
            $this->files->get(resource_path('announcements/maintenance-window/en_US.md')),
        );
        self::assertStringContainsString(
            '[CONFIRM](I have read and accept the updated policy)',
            $this->files->get(resource_path('announcements/new-usage-policy/en_US.md')),
        );
        self::assertStringContainsString(
            '[DECLINE](Ablehnen und abmelden)',
            $this->files->get(resource_path('announcements/new-usage-policy/de_DE.md')),
        );
    }

    public function testItIsIdempotent(): void
    {
        User::factory()->count(2)->create();

        $this->seed(DemoAnnouncementsSeeder::class);

        // Hand-edited content must survive a re-run, and rows must not duplicate.
        $keptFile = resource_path('announcements/maintenance-window/en_US.md');
        $this->files->put($keptFile, '## Hand-edited content');

        $this->seed(DemoAnnouncementsSeeder::class);

        self::assertSame(6, Announcement::query()->whereIn('view', self::DEMO_VIEWS)->count());
        self::assertSame('## Hand-edited content', $this->files->get($keptFile));
    }
}
