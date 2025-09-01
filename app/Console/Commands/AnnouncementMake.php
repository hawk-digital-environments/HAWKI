<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

use Carbon\Carbon;

class AnnouncementMake extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcement:make
                            {title : Announcement Title}';


    protected $description = 'Create a new announcement Blade view';

    public function handle()
    {
        $title = $this->argument('title');
        $view = Str::slug($this->argument('title')); // Make safe filename!
        $time = Carbon::now()->format('Y-m-d-His');
        $name = "{$time}-{$view}";
        $folderName = "announcements/$name";
        // Ensure the directory exists
        if (!is_dir(resource_path($folderName))) {
            mkdir(resource_path($folderName), 0755, true);
        }

        $availableLanguages = config('locale.langs');

        foreach ($availableLanguages as $l) {
            $lang = $l['id'];

            $this->info("Creating announcement file for $lang");

            $filePath = resource_path("$folderName/$lang.md");

            if (!file_exists($filePath)) {
                file_put_contents($filePath,
                    "## $title"
                );
            }
            $relativePath = "resources/$folderName/$lang.md";

            $edit = $this->choice("Announcement file created at $relativePath. Do you like to edit it? y/n",
                                    ["y", "n"],
                                    "n");
            if($edit === "n"){
                continue;
            }


            // Open the file in nano for editing
            $this->info("Opening  in nano...");

            $descriptorspec = [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ];

            $process = proc_open("nano " . escapeshellarg($relativePath), $descriptorspec, $pipes);

            if (is_resource($process)) {
                proc_close($process);
            }
        }

        $this->line("Announcement created with the title:");
        $this->info($title);
        $this->line("Announcement created with the title:  in folder:");
        $this->info("resources/$folderName");
        $this->line("Use these commands to publish the new announcement:");
        $this->info("php artisan announcement:publish");
        $this->info("php hawki announcement -publish");
    }
}
