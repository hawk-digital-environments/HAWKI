<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Announcements\AnnouncementService;


class PublishAnnouncement extends Command
{
    protected $signature = 'announcement:publish
                            {title? : Announcement Title}
                            {view? : The Blade view reference (e.g. announcements.terms_update)}
                            {--type= : Announcement type (policy, news, system, event, info)}
                            {--force= : User must accept the announcement before proceeding (true/false)}
                            {--global= : Make this a global announcement (true/false)}
                            {--users=* : Target user IDs (if not global)}
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

        $users = $global
            ? null
            : ($this->option('users') ?: explode(',', $this->ask('Enter target user IDs (comma-separated)', '')));

        $start = $this->option('start') ?: $this->ask('Enter start datetime (Y-m-d H:i:s)', now()->toDateTimeString());
        $expire = $this->option('expire') ?: $this->ask('Enter expire datetime (Y-m-d H:i:s)', null);

        // Call service
        $announcement = $service->createAnnouncement(
            $title,
            $view,
            $type,
            $force,
            $global,
            $users,
            $start,
            $expire
        );

        $this->info("✅ Announcement [{$announcement->view}] created with ID {$announcement->id}");
    }
}
