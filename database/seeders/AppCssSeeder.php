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

        $cssFiles = File::files($cssDir);
        $count = 0;

        foreach ($cssFiles as $file) {
            if ($file->getExtension() === 'css') {
                $name = $file->getFilenameWithoutExtension();
                $content = File::get($file->getPathname());

                // Only create new CSS entries, never update existing ones to preserve customizations
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
                    $count++;
                }
            }
        }

        $this->command->info("Successfully imported {$count} CSS files from {$cssDir}");
    }

    /**
     * Get description for CSS file based on filename
     */
    private function getDescriptionForCssFile(string $filename): string
    {
        $descriptions = [
            'style' => 'Main application stylesheet containing core styling rules and theme definitions.',
            'custom-styles' => 'Custom CSS styles for additional styling and overrides. Use this for organization-specific customizations.',
            'home-style' => 'Styles specific to the home page layout and components.',
            'chat_modules' => 'CSS styles for chat modules and conversation interfaces.',
            'login_style' => 'Styling for login and authentication pages.',
            'settings_style' => 'Styles for settings and configuration pages.',
            'handshake_style' => 'Styles for handshake and connection establishment interfaces.',
            'print_styles' => 'Print-specific CSS styles for proper document formatting.',
            'hljs_custom' => 'Custom syntax highlighting styles for code blocks.',
        ];

        return $descriptions[$filename] ?? "CSS stylesheet for {$filename} related styling.";
    }
}
