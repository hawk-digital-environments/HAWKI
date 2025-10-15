<?php

namespace Database\Seeders;

use App\Models\Announcements\Announcement;
use App\Models\Announcements\AnnouncementTranslation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class AnnouncementTranslationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder imports existing markdown files from resources/announcements/
     * into the announcement_translations table.
     */
    public function run(): void
    {
        $announcementsPath = resource_path('announcements');
        
        if (!File::isDirectory($announcementsPath)) {
            $this->command->warn("Announcements directory not found: {$announcementsPath}");
            return;
        }

        // Get all announcement folders
        $folders = File::directories($announcementsPath);

        foreach ($folders as $folder) {
            $folderName = basename($folder);
            
            // Find or create the announcement record
            $announcement = Announcement::where('view', $folderName)->first();
            
            if (!$announcement) {
                $this->command->warn("No announcement found with view '{$folderName}'. Skipping...");
                continue;
            }

            // Get all .md files in this folder
            $markdownFiles = File::glob($folder . '/*.md');

            foreach ($markdownFiles as $file) {
                $locale = pathinfo($file, PATHINFO_FILENAME); // e.g., 'de_DE' or 'en_US'
                $content = File::get($file);

                // Create or update translation
                AnnouncementTranslation::updateOrCreate(
                    [
                        'announcement_id' => $announcement->id,
                        'locale' => $locale,
                    ],
                    [
                        'content' => $content,
                    ]
                );

                $this->command->info("Imported: {$folderName}/{$locale}.md");
            }
        }

        $this->command->info('Announcement translations seeded successfully!');
    }
}
