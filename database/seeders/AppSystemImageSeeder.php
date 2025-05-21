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
            'favicon' => 'img/favicon.png',
            'logo_svg' => 'img/logo.svg'
        ];
        
        $count = 0;
        
        foreach ($defaultImages as $name => $path) {
            $fullPath = public_path($path);
            Log::info("Checking for default image: {$path} at {$fullPath}");
            
            if (File::exists($fullPath)) {
                // Get file information
                $originalName = basename($path);
                $mimeType = File::mimeType($fullPath);
                
                // Create or update the database entry
                AppSystemImage::updateOrCreate(
                    ['name' => $name],
                    [
                        'file_path' => $path,
                        'original_name' => $originalName,
                        'mime_type' => $mimeType,
                        'active' => true
                    ]
                );
                
                $count++;
                $this->command->info("Imported image: {$path}");
            } else {
                Log::warning("Default image not found: {$fullPath}");
                $this->command->warn("Default image not found: {$fullPath}");
            }
        }
        
        $this->command->info("Successfully imported {$count} system images");
    }
}