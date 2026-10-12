<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Announcements\AnnouncementService;
use Illuminate\Console\Command;

class AnnouncementPublish extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcement:publish
                            {title? : Announcement Title}
                            {view? : Name of the markdown content folder (e.g. basic-guidelines)}
                            {--type= : Announcement type (policy, news, system, event, info)}
                            {--force= : User must accept the announcement before proceeding (true/false)}
                            {--global= : Make this a global announcement (true/false)}
                            {--users=* : Target user IDs (if not global)}
                            {--anchor= : Anchor Announcement to an special Frontend Event}
                            {--start= : Start datetime (Y-m-d H:i:s)}
                            {--expire= : Expire datetime (Y-m-d H:i:s)}';
    protected $description = 'Create a new announcement entry referencing a content folder';

    private const array TYPES = ['policy', 'news', 'system', 'event', 'info'];

    public function handle(AnnouncementService $service): void
    {
        $interactive = $this->input->isInteractive();

        $title = $this->argument('title') ?: $this->ask('Enter the announcement title');
        $view = $this->argument('view') ?: $this->ask('Enter the content folder name (e.g. basic-guidelines)');

        $type = $this->option('type')
            ?: ($interactive
                ? $this->choice('Select the announcement type', self::TYPES, 4)
                // Same default as the interactive choice (index 4).
                : 'info');

        $force = $this->option('force') !== null
            ? filter_var($this->option('force'), \FILTER_VALIDATE_BOOLEAN)
            : ($interactive ? $this->confirm('Should users be forced to accept this announcement?', true) : true);

        $global = $this->option('global') !== null
            ? filter_var($this->option('global'), \FILTER_VALIDATE_BOOLEAN)
            : ($interactive ? $this->confirm('Is this a global announcement?', true) : true);

        $users = $global
            ? null
            : self::normalizeUsers($this->option('users')
                ?: ($interactive
                    ? explode(',', (string)$this->ask('Enter target user IDs (comma-separated)', ''))
                    : []));

        // The anchor is a frontend event name (e.g. `FileUpload`), not a boolean flag.
        $anchor = $this->option('anchor') !== null
            ? (string)$this->option('anchor')
            : ($interactive ? $this->ask('Enter an anchor (optional, e.g. FileUpload)', null) : null);

        $start = $this->option('start')
            ?: ($interactive
                ? $this->ask('Enter start datetime (Y-m-d H:i:s)', now()->toDateTimeString())
                : now()->toDateTimeString());

        $expire = $this->option('expire')
            ?: ($interactive ? $this->ask('Enter expire datetime (Y-m-d H:i:s)', null) : null);

        // Call service
        $announcement = $service->createAnnouncement(
            $title,
            $view,
            $type,
            $force,
            $global,
            $users,
            $anchor,
            $start,
            $expire,
        );

        $this->info("✅ Announcement [{$announcement->view}] created with ID {$announcement->id}");
    }

    /**
     * Normalizes target user ids whether they arrived as repeated options
     * (`--users=1 --users=2`), comma-joined (`--users=1,2`, interactive answer)
     * or any mix: split on commas, trim, drop empties, reindex.
     *
     * @param array<int, mixed> $users
     * @return list<int>
     */
    private static function normalizeUsers(array $users): array
    {
        $normalized = [];
        foreach ($users as $user) {
            foreach (explode(',', (string)$user) as $id) {
                $id = trim($id);

                if ($id === '') {
                    continue;
                }

                // Ints, not strings: the visibility query uses
                // orWhereJsonContains('target_users', $user->id) with an int id, and
                // MySQL's JSON_CONTAINS does not match numbers against quoted
                // strings — string ids would make the announcement invisible to
                // its own targets.
                $normalized[] = (int)$id;
            }
        }

        return array_values(array_unique($normalized));
    }
}
