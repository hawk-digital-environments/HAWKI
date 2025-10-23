<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Announcements\AnnouncementService;


class AnnouncementPublish extends Command
{
    protected $signature = 'announcement:publish
                            {title? : Announcement Title}
                            {view? : The Blade view reference (e.g. announcements.terms_update)}
                            {--type= : Announcement type (policy, news, system, event, info)}
                            {--force= : User must accept the announcement before proceeding (true/false)}
                            {--global= : Make this a global announcement (true/false)}
                            {--roles=* : Target role slugs (if not global)}
                            {--anchor= : Anchor Announcement to an special Frontend Event}
                            {--start= : Start datetime (Y-m-d H:i:s)}
                            {--expire= : Expire datetime (Y-m-d H:i:s)}';

    protected $description = 'Create a new announcement entry referencing a Blade view';

    public function handle(AnnouncementService $service)
    {
        // Arguments
        $title = $this->argument('title') ?: $this->ask('Enter the announcement title');
        $view  = $this->argument('view') ?: $this->ask('Enter the Blade view reference (e.g. announcements.terms_update)');

        // Options with defaults
        $type = $this->option('type')
            ?: $this->choice('Select the announcement type', ['policy', 'news', 'system', 'event', 'info'], 4);

        $force = $this->option('force') !== null
            ? filter_var($this->option('force'), FILTER_VALIDATE_BOOLEAN)
            : $this->confirm('Should users be forced to accept this announcement?', true);

        $global = $this->option('global') !== null
            ? filter_var($this->option('global'), FILTER_VALIDATE_BOOLEAN)
            : $this->confirm('Is this a global announcement?', true);

        $roles = $global
            ? null
            : ($this->option('roles') ?: explode(',', $this->ask('Enter target role slugs (comma-separated)', '')));

        // Show available anchors from config
        $availableAnchors = config('announcements.anchors', []);
        if (!empty($availableAnchors)) {
            $this->info('Available anchors:');
            foreach ($availableAnchors as $key => $anchorInfo) {
                $this->line("  - {$key}: {$anchorInfo['name']} ({$anchorInfo['description']})");
            }
        }

        $anchor = $this->option('anchor') !== null
            ? filter_var($this->option(('anchor')))
            : ($this->ask('Enter anchor (optional)', null));

        $start = $this->option('start') ?: $this->ask('Enter start datetime (Y-m-d H:i:s)', now()->toDateTimeString());
        $expire = $this->option('expire') ?: $this->ask('Enter expire datetime (Y-m-d H:i:s)', null);

        // Call service
        $announcement = $service->createAnnouncement(
            $title,
            $view,
            $type,
            $force,
            $global,
            $roles,
            $anchor,
            $start,
            $expire
        );

        $this->info("✅ Announcement [{$announcement->view}] created with ID {$announcement->id}");
    }
}
