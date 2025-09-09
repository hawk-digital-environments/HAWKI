<?php

namespace Database\Seeders;

use App\Models\AppSystemText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class AppSystemTextSeeder extends Seeder
{
    /**
     * Seed the application's system texts.
     *
     * @return void
     */
    public function run()
    {
        Log::info('Running AppSystemText seeder...');
        
        $languagePath = resource_path('language/');
        $supportedLanguages = ['de_DE', 'en_US'];
        $count = 0;
        
        // Process each language file
        foreach ($supportedLanguages as $language) {
            $jsonFile = $languagePath . $language . '.json';
            
            if (File::exists($jsonFile)) {
                try {
                    Log::info("Processing {$language} JSON file: {$jsonFile}");
                    $jsonContent = File::get($jsonFile);
                    $textData = json_decode($jsonContent, true);
                    
                    if ($textData && is_array($textData)) {
                        foreach ($textData as $key => $value) {
                            // Skip entries that are not strings or are empty
                            if (!is_string($value) || empty($value)) {
                                continue;
                            }
                            
                            // Store the text in the database using seeder-specific method
                            $result = AppSystemText::setTextIfNotExists($key, $language, $value);
                            // Only count if it was actually created (not if it already existed)
                            if ($result->wasRecentlyCreated) {
                                $count++;
                            }
                        }
                        
                        Log::info("Successfully processed {$language} language file");
                    } else {
                        Log::error("Invalid JSON format in {$jsonFile}");
                    }
                } catch (\Exception $e) {
                    Log::error("Error processing {$jsonFile}: " . $e->getMessage());
                }
            } else {
                Log::warning("Language file not found: {$jsonFile}");
            }
        }
        
        Log::info("AppSystemText seeder completed: {$count} texts created (no updates)");
        $this->command->info("AppSystemText seeder completed: {$count} texts created (no updates)");
    }
}
