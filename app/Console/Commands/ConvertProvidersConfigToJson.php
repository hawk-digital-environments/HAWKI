<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConvertProvidersConfigToJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'providers:config-to-json 
                            {config_file? : Path to the PHP config file (default: config/model_providers.php.example)}
                            {--output= : Output JSON file path (default: storage/app/providers_import.json)}
                            {--pretty : Format JSON with pretty printing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert model_providers.php config file to JSON format for import';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configFile = $this->argument('config_file') ?? 'config/model_providers.php.example';
        $outputFile = $this->option('output') ?? 'storage/app/providers_import.json';
        $prettyPrint = $this->option('pretty');

        // Resolve absolute paths
        $configPath = base_path($configFile);
        $outputPath = base_path($outputFile);

        // Check if config file exists
        if (!File::exists($configPath)) {
            $this->error("Config file not found: {$configPath}");
            $this->line("Available config files:");
            $this->line("  config/model_providers.php.example");
            $this->line("  config/model_providers.php");
            return Command::FAILURE;
        }

        $this->info("Converting PHP config to JSON...");
        $this->line("Source: {$configPath}");
        $this->line("Output: {$outputPath}");

        try {
            // Load the PHP config file
            $config = include $configPath;
            
            if (!is_array($config) || !isset($config['providers'])) {
                $this->error("Invalid config file format. Expected array with 'providers' key.");
                return Command::FAILURE;
            }

            $providers = $config['providers'];
            $convertedProviders = [];

            $this->info("Converting " . count($providers) . " providers...");

            foreach ($providers as $providerKey => $providerData) {
                // Convert from model_providers.php format to ProviderSetting format
                $convertedProvider = [
                    'provider_name' => $providerData['id'] ?? $providerKey,
                    'api_key' => $providerData['api_key'] ?? '',
                    'base_url' => $providerData['api_url'] ?? '',
                    'is_active' => true
                ];

                // Build additional_settings from remaining fields
                $additionalSettings = [];
                
                if (!empty($providerData['ping_url'])) {
                    $additionalSettings['ping_url'] = $providerData['ping_url'];
                }

                // Add any other fields that are not part of the main ProviderSetting structure
                $mainFields = ['id', 'api_key', 'api_url'];
                foreach ($providerData as $key => $value) {
                    if (!in_array($key, $mainFields) && $key !== 'ping_url') {
                        $additionalSettings[$key] = $value;
                    }
                }

                // Convert additional_settings to JSON string if not empty
                if (!empty($additionalSettings)) {
                    $convertedProvider['additional_settings'] = json_encode($additionalSettings);
                } else {
                    $convertedProvider['additional_settings'] = null;
                }

                $convertedProviders[] = $convertedProvider;
                
                $this->line("  ✓ Converted: {$convertedProvider['provider_name']}");
            }

            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (!File::exists($outputDir)) {
                File::makeDirectory($outputDir, 0755, true);
            }

            // Convert to JSON
            $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            if ($prettyPrint) {
                $jsonFlags |= JSON_PRETTY_PRINT;
            }

            $jsonContent = json_encode($convertedProviders, $jsonFlags);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("JSON encoding failed: " . json_last_error_msg());
                return Command::FAILURE;
            }

            // Write to file
            File::put($outputPath, $jsonContent);

            $this->newLine();
            $this->info("✓ Successfully converted {$configFile} to JSON!");
            $this->line("Output file: {$outputPath}");
            $this->line("File size: " . File::size($outputPath) . " bytes");
            
            $this->newLine();
            $this->line("You can now import this JSON file using the Provider Settings Import function.");
            
            // Show preview of first provider if pretty print is enabled
            if ($prettyPrint && count($convertedProviders) > 0) {
                $this->newLine();
                $this->line("Preview of first provider:");
                $this->line(json_encode($convertedProviders[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error during conversion: " . $e->getMessage());
            $this->line("Stack trace:");
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
