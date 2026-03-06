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
        $cssDir = public_path('css_v2.1.0');

        if (! File::exists($cssDir)) {
            Log::warning("CSS directory not found: {$cssDir}");
            $this->command->info("CSS directory not found: {$cssDir}");

            return;
        }

        // Only import custom-styles.css
        $customStylesFile = $cssDir.'/custom-styles.css';
        
        if (! File::exists($customStylesFile)) {
            Log::warning("custom-styles.css not found in: {$cssDir}");
            $this->command->info("custom-styles.css not found in: {$cssDir}");

            return;
        }

        $name = 'custom-styles';
        $content = File::get($customStylesFile);
        $description = $this->getDescriptionForCssFile($name);

        $existing = AppCss::where('name', $name)->first();

        if ($existing) {
            $this->command->info("Skipping existing CSS file: {$name} (preserving customizations)");
        } else {
            AppCss::create([
                'name' => $name,
                'content' => $content,
                'description' => $description,
                'active' => true,
            ]);
            $this->command->info("Successfully imported custom-styles.css from {$cssDir}");
        }
    }

    /**
     * Get description for CSS file based on filename
     */
    private function getDescriptionForCssFile(string $filename): string
    {
        $descriptions = [
            'custom-styles' => 'Custom CSS styles for additional styling and overrides. Use this for organization-specific customizations.',
        ];

        return $descriptions[$filename] ?? 'Custom CSS stylesheet';
    }
}
