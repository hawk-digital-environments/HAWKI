<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class AnnouncementMake extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcement:make
                            {title : Announcement Title}';
    protected $description = 'Scaffold the per-locale markdown content files for a new announcement';

    public function handle(Filesystem $files): void
    {
        $title = (string)$this->argument('title');
        // The folder name is the announcement's `view` — the API resolves content
        // from `resources/announcements/{view}/{lang}.md`, so it must be the slug,
        // not a timestamped name.
        $view = Str::slug($title);
        $folder = resource_path("announcements/{$view}");

        $files->ensureDirectoryExists($folder);

        foreach ($this->localeIds() as $lang) {
            $this->info("Creating announcement file for {$lang}");

            $filePath = "{$folder}/{$lang}.md";
            $relativePath = "resources/announcements/{$view}/{$lang}.md";

            // Never overwrite hand-written content.
            if (!$files->exists($filePath)) {
                $files->put($filePath, "## {$title}");
            }

            // The interactive editor only makes sense on a real terminal; skip it
            // entirely for non-interactive runs (cron, CI, tests).
            if (!$this->input->isInteractive()) {
                continue;
            }

            $edit = $this->choice(
                "Announcement file created at {$relativePath}. Do you like to edit it? y/n",
                ['y', 'n'],
                'n',
            );

            if ('n' === $edit) {
                continue;
            }

            // Open the file in nano for editing
            $this->info('Opening in nano...');

            $process = proc_open(
                'nano ' . escapeshellarg($relativePath),
                [0 => \STDIN, 1 => \STDOUT, 2 => \STDERR],
                $pipes,
            );

            if (\is_resource($process)) {
                proc_close($process);
            }
        }

        $this->line('Announcement content created:');
        $this->line("  title: {$title}");
        $this->line("  view:  {$view} (folder `resources/announcements/{$view}`)");
        $this->line('Publish it with:');
        $this->info('php artisan announcement:publish');
        $this->info('php hawki announcement:publish');
    }

    /**
     * @return list<string> Locale ids a content file is scaffolded for.
     */
    private function localeIds(): array
    {
        $langs = config('locale.langs');

        $ids = [];
        foreach ((array)$langs as $lang) {
            $ids[] = (string)($lang['id'] ?? '');
        }

        return array_values(array_filter($ids));
    }
}
