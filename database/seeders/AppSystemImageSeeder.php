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
            'favicon' => 'favicon.ico',
            'logo_svg' => 'img/logo.svg'
        ];
        
        $count = 0;
        
        foreach ($defaultImages as $name => $path) {
            if (File::exists(public_path($path))) {
                // Get file information
                $originalName = basename($path);
                $mimeType = File::mimeType(public_path($path));
                
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
            } else {
                Log::warning("Default image not found: {$path}");
                $this->command->warn("Default image not found: {$path}");
            }
        }
        
        $this->command->info("Successfully imported {$count} system images");
    }
}