<?php

namespace Database\Seeders;

use App\Models\AppCss;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AppCssSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cssDir = public_path('css_v2.0.1_f1');
        
        if (!File::exists($cssDir)) {
            Log::warning("CSS directory not found: {$cssDir}");
            $this->command->info("CSS directory not found: {$cssDir}");
            return;
        }
        
        $cssFiles = File::files($cssDir);
        $count = 0;
        
        foreach ($cssFiles as $file) {
            if ($file->getExtension() === 'css') {
                $name = $file->getFilenameWithoutExtension();
                $content = File::get($file->getPathname());
                
                AppCss::updateOrCreate(
                    ['name' => $name],
                    ['content' => $content]
                );
                
                $count++;
            }
        }
        
        $this->command->info("Successfully imported {$count} CSS files from {$cssDir}");
    }
}
