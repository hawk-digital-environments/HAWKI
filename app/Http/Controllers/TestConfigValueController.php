<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class TestConfigValueController extends Controller
{
    /**
     * Return configuration values for testing
     *
     * @return JsonResponse
     */
    public function __invoke(): JsonResponse
    {
        // Lade Konfigurationseinstellungen aus settings.php
        $configSettings = config('settings');
        
        if (empty($configSettings)) {
            return response()->json([
                'error' => 'No configuration settings found in settings.php',
                'hint' => 'Check that the settings.php file exists and contains configuration definitions.'
            ], 500);
        }
        
        // Erstelle eine Liste aller zu überwachenden Konfigurationsschlüssel
        $configFiles = array_keys($configSettings);
        $dbKeys = [];
        $configKeyMap = [];
        
        foreach ($configSettings as $configFile => $keys) {
            // Überspringe das Gruppen-Mapping - das ist ein spezieller Konfigurationsschlüssel
            if ($configFile === 'group_mapping') {
                continue;
            }
            
            if (!is_array($keys)) continue;
            
            foreach ($keys as $key => $value) {
                // Wenn der Schlüssel numerisch ist, verwenden wir den Wert als Schlüssel und keine Beschreibung
                if (is_int($key)) {
                    $realKey = $value;
                    $description = null;
                } else {
                    // Ansonsten ist der Key der Schlüssel und der Wert die Beschreibung
                    $realKey = $key;
                    $description = $value;
                }
                
                $dbKey = "{$configFile}_{$realKey}";
                $configKey = "{$configFile}.{$realKey}";
                $dbKeys[] = $dbKey;
                $configKeyMap[$dbKey] = $configKey;
                
            }
        }
        
        // Wenn keine Schlüssel definiert sind, früh beenden
        if (empty($dbKeys)) {
            return response()->json([
                'warning' => 'No configuration keys defined in settings.php',
                'config_values' => [],
                'settings_info' => [
                    'config_files' => $configFiles,
                    'db_keys_mapped' => 0,
                    'settings_found' => 0
                ]
            ]);
        }
        
        // Exakt nur die definierten Schlüssel aus der DB laden
        $dbSettings = DB::table('app_settings')
                       ->whereIn('key', $dbKeys)
                       ->get();
        
        $result = [];
        
        // Sortierte Ergebnisliste vorbereiten - basierend auf den definierten Keys in settings.php
        foreach ($dbKeys as $dbKey) {
            $configKey = $configKeyMap[$dbKey];
            
            // Initialisiere das Ergebnis mit Standardwerten
            $result[$configKey] = [
                'config_value' => 'Not defined',
                'env_value' => 'Not defined',
                'db_value' => 'Not found in database', 
                'config_source' => 'missing value',
                'db_key' => $dbKey,
                'description' => $description,
            ];
        }
        
        // Verarbeite die gefundenen Datenbankeinstellungen
        foreach ($dbSettings as $dbSetting) {
            // DB-Key im Unterstrich-Format
            $dbKey = $dbSetting->key;
            
            // Überspringe, wenn der Key nicht in unserer Mapping-Liste ist
            if (!isset($configKeyMap[$dbKey])) continue;
            
            // Bestimme den Config-Schlüssel
            $configKey = $configKeyMap[$dbKey];
            
            // Get value from config without fallback
            $configValue = Config::get($configKey);
            
            // Get value from environment without fallback
            $envKey = $this->getEnvKeyFromConfigKey($configKey);
            $envValue = env($envKey);
            
            // Wert aus Datenbank basierend auf Typ konvertieren
            $dbValue = $dbSetting->value;
            $dbType = $dbSetting->type;
            
            if ($dbValue !== null) {
                switch ($dbType) {
                    case 'boolean':
                        $dbValue = filter_var($dbValue, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'integer':
                        $dbValue = (int) $dbValue;
                        break;
                    case 'json':
                        $dbValue = json_decode($dbValue, true);
                        break;
                }
            }
            
            // Spezielle Behandlung für boolesche Werte bei der Anzeige
            if ($dbType === 'boolean') {
                if (is_bool($configValue)) {
                    $configValue = $configValue ? 'true' : 'false';
                }
                if (is_bool($dbValue)) {
                    $dbValue = $dbValue ? 'true' : 'false';
                } elseif (is_string($dbValue) && ($dbValue === 'true' || $dbValue === 'false')) {
                    $dbValue = filter_var($dbValue, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                }
                if (is_bool($envValue)) {
                    $envValue = $envValue ? 'true' : 'false';
                }
            }
            
            // Format values for display - handle arrays first
            if (is_array($configValue)) {
                $configValueFormatted = json_encode($configValue);
            } else {
                $configValueFormatted = (!is_null($configValue) && $configValue !== '') ? (string)$configValue : 'Not defined';
            }
            
            if (is_array($envValue)) {
                $envValueFormatted = json_encode($envValue);
            } else {
                $envValueFormatted = (!is_null($envValue) && $envValue !== '') ? (string)$envValue : 'Not defined';
            }
            
            if (is_array($dbValue)) {
                $dbValueFormatted = json_encode($dbValue);
            } else {
                $dbValueFormatted = (!is_null($dbValue) && $dbValue !== '') ? (string)$dbValue : 'NULL';
            }
            
            // Bestimme die Quelle des Konfigurationswertes
            $valueSource = 'missing value';
            if (!is_null($dbValue) && $dbValue !== '' && $configValue == $dbValue) {
                $valueSource = 'database';
            } elseif (!is_null($envValue) && $envValue !== '' && $configValue == $envValue) {
                $valueSource = 'environment';
            } elseif (!is_null($configValue) && $configValue !== '') {
                $valueSource = 'default';
            }
            
            // Bei der Ergebniserstellung den Config-Key (Punkt-Notation) verwenden
            $result[$configKey] = [
                'config_value' => $configValueFormatted,
                'env_value' => $envValueFormatted,
                'db_value' => $dbValueFormatted,
                'config_source' => $valueSource,
                'db_key' => $dbKey,
                'db_metadata' => [
                    'type' => $dbSetting->type,
                    'group' => $dbSetting->group,
                    'description' => $dbSetting->description,
                    'source' => $dbSetting->source,
                    'is_private' => (bool) $dbSetting->is_private,
                ],
            ];
        }
        
        // Informationen über die Konfiguration
        $configInfo = [];
        foreach ($configSettings as $file => $keys) {
            if (is_array($keys)) {
                $configInfo[$file] = count($keys) . ' keys';
            }
        }
        
        return response()->json([
            'config_values' => $result,
            'settings_info' => [
                'config_files' => $configFiles,
                'db_keys_mapped' => count($dbKeys),
                'settings_found' => $dbSettings->count(),
                'config_structure' => $configInfo
            ]
        ]);
    }
    
    /**
     * Convert config key format to environment variable format
     * 
     * @param string $configKey Config key in dot notation (e.g., 'app.name')
     * @return string Environment variable name (e.g., 'APP_NAME')
     */
    private function getEnvKeyFromConfigKey(string $configKey): string
    {
        // Special case mappings that don't follow the standard conversion rules
        $specialCases = [
            'sanctum.allow_external_communication' => 'ALLOW_EXTERNAL_COMMUNICATION',
            'auth.authentication_method' => 'AUTHENTICATION_METHOD',
            'auth.local_authentication' => 'LOCAL_AUTHENTICATION',
            'session.lifetime' => 'SESSION_LIFETIME',
            'ldap.default' => 'LDAP_CONNECTION',
            'ldap.connections.default.hosts' => 'LDAP_HOST',
        ];
        
        if (isset($specialCases[$configKey])) {
            return $specialCases[$configKey];
        }
        
        // Standard conversion: replace dots with underscores and convert to uppercase
        return strtoupper(str_replace('.', '_', $configKey));
    }
}
