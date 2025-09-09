<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class AppSettingsSeeder extends Seeder
{
    /**
    * Mapping of groups based on configuration file names
     * 
     * @var array
     */
    protected $groupMapping = [
        'app' => 'basic',
        'sanctum' => 'api',
        'auth' => 'authentication',
        'ldap' => 'authentication',
        'open_id_connect' => 'authentication',
        'shibboleth' => 'authentication',
        'session' => 'authentication',
    ];
    
    /**
    * Loads the database seeder.
     *
     * @return void
     */
    public function run()
    {
        // Lade Konfigurationseinstellungen aus settings.php
        $settingsConfig = Config::get('settings', []);
        
        if (empty($settingsConfig)) {
            $this->command->warn("No configuration settings found in settings.php");
            return;
        }
        
        // Verarbeite alle konfigurierten Dateien aus settings.php
        foreach ($settingsConfig as $configName => $keysToProcess) {
            // Überspringe das Gruppen-Mapping - das ist ein spezieller Konfigurationsschlüssel
            if ($configName === 'group_mapping') {
                continue;
            }
            
            if (!is_array($keysToProcess) || empty($keysToProcess)) {
                $this->command->info("Skipping {$configName}: No keys defined or not an array");
                continue;
            }
            
            $this->processConfigFile($configName, $keysToProcess);
        }
    }
    
    /**
    * Processes a configuration file and imports its settings
     *
     * @param string $configName
     * @param array $keysToProcess
     * @return void
     */
    private function processConfigFile($configName, array $keysToProcess)
    {
        // Lade die Konfiguration
        $configData = config($configName);
        
        if (!is_array($configData)) {
            $this->command->warn("Config {$configName} is not an array, skipping");
            return;
        }
        
        // Hole das Gruppen-Mapping aus der Konfiguration
        $groupMappings = config('settings.group_mapping', []);
        // Bestimme die Gruppe für diese Konfigurationsdatei oder verwende 'basic' als Fallback
        $group = $groupMappings[$configName] ?? 'basic';
        
        // Restlicher Code für die Dateiverarbeitung
        $filePath = config_path("{$configName}.php");
        $envVariables = $this->extractEnvVariables($filePath);
        
        $flattenedConfig = $this->flattenArray($configData);
        $processedCount = 0;
        
        // Nur die in settings.php definierten Schlüssel verarbeiten
        foreach ($keysToProcess as $key => $value) {
            // Wenn der Schlüssel numerisch ist, verwenden wir den Wert als Schlüssel und keine Beschreibung
            if (is_int($key)) {
                $realKey = $value;
                $customDescription = null;
            } else {
                // Ansonsten ist der Key der Schlüssel und der Wert die Beschreibung
                $realKey = $key;
                $customDescription = $value;
            }
            
            // Prüfen, ob der Schlüssel in der geflachten Konfiguration existiert
            if (!array_key_exists($realKey, $flattenedConfig)) {
                $this->command->warn("Key '{$realKey}' not found in {$configName} configuration");
                continue;
            }
            
            $value = $flattenedConfig[$realKey];
            
            // Standardwert für authentication_method setzen, wenn null oder leer
            if ($realKey === 'authentication_method' && $configName === 'auth' && (is_null($value) || $value === '')) {
                $value = 'LDAP';
            }
            
            // DB-Key erstellen (mit Unterstrich): app_name
            $dbKey = "{$configName}_{$realKey}";
            
            //$configFullKey = "{$configName}.{$realKey}";
            $type = $this->determineType($value, $dbKey);

            // Verwende die benutzerdefinierte Beschreibung, falls vorhanden
            $description = $customDescription ?: ('');
            
            $this->createOrUpdateSetting(
                $dbKey,
                $value,
                $group,
                $type,
                $description,
                false,
                $configName
            );
            
            $processedCount++;
        }
        
        $this->command->info("Processed {$processedCount} keys from {$configName}");
    }
    
    /**
    * Extracts env variables from a configuration file
     *
     * @param string $filePath
     * @return array
     */
    private function extractEnvVariables($filePath)
    {
        if (!File::exists($filePath)) {
            return [];
        }
        
        $content = File::get($filePath);
        $envVars = [];
        
        // Regex um env('KEY', default) Muster zu finden
        preg_match_all("/env\(['\"]([^'\"]+)['\"]/", $content, $matches, PREG_OFFSET_CAPTURE);
        
        if (isset($matches[1])) {
            foreach ($matches[1] as $match) {
                $envVars[] = [
                    'key' => $match[0],
                    'position' => $match[1]
                ];
            }
        }
        
        return $envVars;
    }
    
    /**
    * Converts a multidimensional array into a flat array with dot notation
     *
     * @param array $array
     * @param string $prefix
     * @return array
     */
    private function flattenArray($array, $prefix = '')
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            // Env-Funktionsaufrufe überspringen
            if (is_object($value) && get_class($value) === 'Closure') {
                continue;
            }
            
            if (is_array($value) && !$this->isAssociative($value)) {
                // Indexierte Arrays als JSON behandeln
                $result[$newKey] = $value;
            } elseif (is_array($value) && !empty($value)) {
                // Assoziative Arrays rekursiv abflachen
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }
    
    /**
    * Checks if an array is associative
     *
     * @param array $array
     * @return bool
     */
    private function isAssociative($array)
    {
        if (!is_array($array)) {
            return false;
        }
        
        return array_keys($array) !== range(0, count($array) - 1);
    }
    
    /**
    * Determines the data type of a value
     *
     * @param mixed $value
     * @return string
     */
     private function determineType($value, $key = null)
     {

        if (is_bool($value)) {
            return 'boolean';
        }
        
        if (is_int($value) || (is_numeric($value) && (string)(int)$value === (string)$value)) {
            return 'integer';
        }
        
        if (is_array($value)) {
            return 'json';
        }
        
        return 'string';
    }
    
    /**
    * Creates or updates a setting
     *
     * @param string $key
     * @param mixed $value
     * @param string $group
     * @param string $type
     * @param string|null $description
     * @param bool $isPrivate
     * @param string|null $source
     * @return void
     */
    private function createOrUpdateSetting($key, $value, $group, $type, $description = null, $isPrivate = false, $source = null)
    {
        // Convert value for storage
        if ($type === 'json' && is_array($value)) {
            $value = json_encode($value);
        } elseif ($type === 'boolean') {
            $value = $value ? 'true' : 'false';
        } elseif ($value !== null) {
            $value = (string) $value;
        }
        
        AppSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'source' => $source,
                'group' => $group,
                'type' => $type,
                'description' => $description,
                'is_private' => $isPrivate,
            ]
        );
    }
}
