<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\AnnouncementMake;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Feature test of {@see AnnouncementMake}: scaffolds per-locale markdown content
 * under `resources/announcements/{slug}/`. Tests run against the real resource
 * folder and remove their scaffolds in tearDown.
 */
#[CoversClass(AnnouncementMake::class)]
class AnnouncementMakeTest extends TestCase
{
    private const string BASE_PATH = 'resources/announcements';
    private Filesystem $files;

    /**
     * @var list<string> Folders created during the test, removed in tearDown.
     */
    private array $createdFolders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = $this->app->make(Filesystem::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFolders as $folder) {
            $this->files->deleteDirectory(self::BASE_PATH . '/' . $folder);
        }

        parent::tearDown();
    }

    public function testItScaffoldsPerLocaleContentFilesForTheSlugFolder(): void
    {
        $this->artisan('announcement:make', ['title' => 'Make Command Sample', '--no-interaction' => true])
            ->assertExitCode(0);

        $folder = self::BASE_PATH . '/make-command-sample';
        self::assertDirectoryExists($folder);

        foreach ($this->configuredLocaleIds() as $localeId) {
            $file = "{$folder}/{$localeId}.md";
            self::assertFileExists($file);
            self::assertSame('## Make Command Sample', $this->files->get($file));
        }

        $this->createdFolders[] = 'make-command-sample';
    }

    public function testItSlugsSpecialCharactersOutOfTheViewName(): void
    {
        $this->artisan('announcement:make', ['title' => 'Grundlagen & Updates!', '--no-interaction' => true])
            ->assertExitCode(0);

        self::assertDirectoryExists(self::BASE_PATH . '/grundlagen-updates');

        $this->createdFolders[] = 'grundlagen-updates';
    }

    public function testItKeepsExistingContentFiles(): void
    {
        $folder = self::BASE_PATH . '/make-command-keep-existing';
        $localeIds = $this->configuredLocaleIds();
        $keptLocale = $localeIds[0];
        $scaffoldedLocale = $localeIds[1] ?? $keptLocale;

        $this->files->ensureDirectoryExists($folder);
        $this->files->put("{$folder}/{$keptLocale}.md", '## Hand-written content');

        $this->artisan('announcement:make', ['title' => 'Make Command Keep Existing', '--no-interaction' => true])
            ->assertExitCode(0);

        // Existing content survives; the remaining locales are still scaffolded.
        self::assertSame(
            '## Hand-written content',
            $this->files->get("{$folder}/{$keptLocale}.md"),
        );

        if ($scaffoldedLocale !== $keptLocale) {
            self::assertSame(
                '## Make Command Keep Existing',
                $this->files->get("{$folder}/{$scaffoldedLocale}.md"),
            );
        }

        $this->createdFolders[] = 'make-command-keep-existing';
    }

    public function testItRunsNonInteractivelyWithoutPrompts(): void
    {
        // The editor prompt must be skipped entirely on non-interactive runs;
        // this test mainly guards against the command hanging on a prompt.
        $this->artisan('announcement:make', ['title' => 'Make Command Silent', '--no-interaction' => true])
            ->assertExitCode(0);

        self::assertDirectoryExists(self::BASE_PATH . '/make-command-silent');

        $this->createdFolders[] = 'make-command-silent';
    }

    /**
     * @return list<string> locale ids the command is expected to scaffold
     */
    private function configuredLocaleIds(): array
    {
        $ids = array_column((array) config('locale.langs'), 'id');

        return array_values(array_map(static fn (string $id): string => $id, $ids));
    }
}
