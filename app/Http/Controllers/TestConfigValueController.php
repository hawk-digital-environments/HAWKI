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
        // Define the mapping between database keys and config keys
        $configMappings = [
            'APP_NAME' => 'app.name',
            'APP_ENV' => 'app.env',
            'APP_DEBUG' => 'app.debug',
            'APP_URL' => 'app.url',
            'APP_TIMEZONE' => 'app.timezone',
            'APP_LOCALE' => 'app.locale',
            'AI_MENTION_HANDLE' => 'app.aiHandle',
            'ALLOW_EXTERNAL_COMMUNICATION' => 'sanctum.allow_external_communication',

        ];
        
        // Define the exact environment variable names for each config key
        $envMappings = [
            'app.name' => 'APP_NAME',
            'app.env' => 'APP_ENV',
            'app.debug' => 'APP_DEBUG',
            'app.url' => 'APP_URL',
            'app.timezone' => 'APP_TIMEZONE',
            'app.locale' => 'APP_LOCALE',
            'app.aiHandle' => 'AI_MENTION_HANDLE',
            'sanctum.allow_external_communication' => 'ALLOW_EXTERNAL_COMMUNICATION',
        ];
        
        $result = [];
        
        foreach ($configMappings as $dbKey => $configKey) {
            // Get value from config without fallback
            $configValue = Config::get($configKey);
            
            // Get value from environment without fallback
            $envKey = $envMappings[$configKey] ?? $dbKey;
            $envValue = env($envKey);
            
            // Special handling for AI_MENTION_HANDLE
            if ($dbKey === 'AI_MENTION_HANDLE' && !empty($envValue)) {
                $envValue = '@' . $envValue;
            }
            
            // Get value from database
            $dbValue = DB::table('app_settings')->where('key', $dbKey)->value('value');
            
            // Special handling for boolean values when displaying
            if ($dbKey === 'APP_DEBUG') {
                if (is_bool($configValue)) {
                    $configValue = $configValue ? 'true' : 'false';
                }
                if (is_string($dbValue) && ($dbValue === 'true' || $dbValue === 'false')) {
                    $dbValue = filter_var($dbValue, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                }
                if (is_bool($envValue)) {
                    $envValue = $envValue ? 'true' : 'false';
                }
            }
            
            // Special handling for aiHandle to match how ConfigServiceProvider formats it
            if ($dbKey === 'AI_MENTION_HANDLE' && $dbValue !== null) {
                $dbValue = '@' . $dbValue;
            }
            
            // Format values for display - treat empty strings as 'Not defined'
            $configValueFormatted = (!is_null($configValue) && $configValue !== '') ? (string)$configValue : 'Not defined';
            $envValueFormatted = (!is_null($envValue) && $envValue !== '') ? (string)$envValue : 'Not defined';
            $dbValueFormatted = (!is_null($dbValue) && $dbValue !== '') ? (string)$dbValue : 'Not found in database';
            
            // Determine the source of the config value - consider empty strings as missing values
            $valueSource = 'missing value';
            if (!is_null($dbValue) && $dbValue !== '' && $configValue == $dbValue) {
                $valueSource = 'database';
            } elseif (!is_null($envValue) && $envValue !== '' && $configValue == $envValue) {
                $valueSource = 'environment';
            } elseif (!is_null($configValue) && $configValue !== '') {
                $valueSource = 'default';
            }
            
            $result[$configKey] = [
                'config_value' => $configValueFormatted,
                'env_value' => $envValueFormatted,
                'db_value' => $dbValueFormatted,
                'config_source' => $valueSource,
            ];
        }
        
        return response()->json([
            'config_values' => $result,
        ]);
    }
}
