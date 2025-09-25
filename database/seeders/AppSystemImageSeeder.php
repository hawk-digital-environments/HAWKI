<?php

namespace Database\Seeders;

use App\Models\AppSystemImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AppSystemImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultImages = [
            'favicon' => [
                'path' => 'img/favicon.png',
                'description' => 'Browser tab icon displayed in the browser tab and bookmarks. Supports ICO and PNG formats.',
            ],
            'logo_svg' => [
                'path' => 'img/logo.svg',
                'description' => 'Vector version of the application logo used in headers and branding. Supports SVG format for scalability.',
            ],
        ];

        $count = 0;

        foreach ($defaultImages as $name => $config) {
            $path = $config['path'];
            $description = $config['description'];
            $fullPath = public_path($path);
            Log::info("Checking for default image: {$path} at {$fullPath}");

            if (File::exists($fullPath)) {
                // Get file information
                $originalName = basename($path);
                $mimeType = File::mimeType($fullPath);

                // Create database entry only if it doesn't exist
                AppSystemImage::firstOrCreate(
                    ['name' => $name],
                    [
                        'file_path' => $path,
                        'original_name' => $originalName,
                        'mime_type' => $mimeType,
                        'description' => $description,
                        'active' => true,
                    ]
                );

                $count++;
                $this->command->info("Imported image: {$path} with description: {$description}");
            } else {
                Log::warning("Default image not found: {$fullPath}");
                $this->command->warn("Default image not found: {$fullPath}");
            }
        }

        $this->command->info("Successfully imported {$count} system images");
    }
}
