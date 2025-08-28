<?php

namespace App\Orchid\Traits;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Orchid\Support\Facades\Toast;

/**
 * Trait OrchidSettingsManagementTrait
 * 
 * Provides comprehensive settings management functionality for Orchid admin screens.
 * Combines form field generation, data conversion, and database persistence operations.
 * 
 * Key Features:
 * - Database key format conversion (app_name ↔ app.name)
 * - Flat form input name handling (prevents nested objects in frontend)
 * - Settings persistence with change detection
 * - Nested settings processing (LDAP, OIDC, Mail configurations)
 * - Cache invalidation and configuration clearing
 * 
 * @package App\Orchid\Traits
 */
trait OrchidSettingsManagementTrait
{
    // ========================================================================================
    // MODEL MANAGEMENT METHODS
    // ========================================================================================

    /**
     * Save a model with comprehensive change detection, logging, and user feedback
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $data Validated data to fill the model
     * @param string $modelDisplayName Display name for logging and messages
     * @param array $originalValues Original values for change tracking
     * @param callable|null $beforeSave Optional callback before saving
     * @param callable|null $afterSave Optional callback after saving
     * @return array ['hasChanges' => bool, 'changes' => array]
     */
    protected function saveModelWithChangeDetection($model, array $data, string $modelDisplayName, array $originalValues = [], callable $beforeSave = null, callable $afterSave = null): array
    {
        // Store original values if not provided
        if (empty($originalValues)) {
            $originalValues = $model->getOriginal();
        }

        // Fill model with new data
        $model->fill($data);

        // Execute before save callback if provided
        if ($beforeSave) {
            $beforeSave($model, $data);
        }

        // Save the model
        $model->save();

        // Execute after save callback if provided
        if ($afterSave) {
            $afterSave($model, $data);
        }

        // Detect changes
        $changes = $this->detectModelChanges($model, $originalValues);
        $hasChanges = !empty($changes);

        // Log and provide feedback
        $this->logModelChanges($model, $modelDisplayName, $changes, $hasChanges);
        $this->provideSaveFeedback($modelDisplayName, $hasChanges);

        return [
            'hasChanges' => $hasChanges,
            'changes' => $changes
        ];
    }

    /**
     * Detect changes in a model by comparing original and current values
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $originalValues
     * @return array
     */
    protected function detectModelChanges($model, array $originalValues): array
    {
        $changes = [];
        $currentValues = $model->getAttributes();

        foreach ($currentValues as $key => $newValue) {
            $originalValue = $originalValues[$key] ?? null;

            // Special handling for JSON fields
            if ($this->isJsonField($model, $key)) {
                $originalValue = is_string($originalValue) ? json_decode($originalValue, true) : $originalValue;
                $newValue = is_string($newValue) ? json_decode($newValue, true) : $newValue;
            }

            // Compare values
            if ($originalValue !== $newValue) {
                $changes[$key] = [
                    'from' => $originalValue,
                    'to' => $newValue
                ];
            }
        }

        return $changes;
    }

    /**
     * Log model changes with structured format
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $modelDisplayName
     * @param array $changes
     * @param bool $hasChanges
     */
    protected function logModelChanges($model, string $modelDisplayName, array $changes, bool $hasChanges): void
    {
        $logData = [
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'model_name' => $modelDisplayName,
            'changes' => $changes,
            'updated_by' => auth()->id(),
        ];

        if ($hasChanges) {
            Log::info("{$this->getModelTypeName($model)} updated successfully - {$modelDisplayName}", $logData);
        } else {
            Log::debug("{$this->getModelTypeName($model)} saved without changes - {$modelDisplayName}", $logData);
        }
    }

    /**
     * Provide user feedback based on whether changes were made
     *
     * @param string $modelDisplayName
     * @param bool $hasChanges
     */
    protected function provideSaveFeedback(string $modelDisplayName, bool $hasChanges): void
    {
        if ($hasChanges) {
            Toast::success("'{$modelDisplayName}' has been updated successfully.");
        } else {
            Toast::info("No changes detected for '{$modelDisplayName}'.");
        }
    }

    /**
     * Validate and process JSON field with proper error handling
     *
     * @param string $jsonString
     * @param string $fieldName
     * @param int|null $modelId
     * @return array|null Returns decoded array on success, null on failure
     */
    protected function validateAndProcessJsonField(string $jsonString, string $fieldName, int $modelId = null): ?array
    {
        if (empty($jsonString)) {
            return null;
        }

        $decoded = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("Invalid JSON in {$fieldName}", [
                'model_id' => $modelId,
                'field_name' => $fieldName,
                'json_error' => json_last_error_msg(),
                'input' => $jsonString,
            ]);
            
            Toast::error("{$fieldName} must be valid JSON format.");
            return false; // Return false to indicate validation error
        }

        return $decoded;
    }

    /**
     * Get a user-friendly model type name for logging
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return string
     */
    protected function getModelTypeName($model): string
    {
        $className = class_basename($model);
        
        // Convert CamelCase to human-readable format
        return preg_replace('/(?<!^)[A-Z]/', ' $0', $className);
    }

    /**
     * Check if a model field should be treated as JSON
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $field
     * @return bool
     */
    protected function isJsonField($model, string $field): bool
    {
        // Check if model has casts defined for this field
        $casts = $model->getCasts();
        
        return isset($casts[$field]) && in_array($casts[$field], ['array', 'json', 'object', 'collection']);
    }

    /**
     * Clear all records from a specified model class.
     * Provides standardized logging and user feedback for bulk delete operations.
     *
     * @param string $modelClass The fully qualified model class name
     * @param string $modelDisplayName Human-readable name for user feedback
     * @param array $whereConditions Optional where conditions for selective deletion
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function clearAllModelsOfType(string $modelClass, string $modelDisplayName, array $whereConditions = [])
    {
        try {
            // Build query
            $query = $modelClass::query();
            
            // Apply where conditions if provided
            if (!empty($whereConditions)) {
                foreach ($whereConditions as $field => $value) {
                    $query->where($field, $value);
                }
            }
            
            // Get count before deletion for logging
            $totalModels = $query->count();
            
            if ($totalModels === 0) {
                Toast::info("No {$modelDisplayName} found to clear.");
                return redirect()->back();
            }

            // Delete records
            $query->delete();

            // Use trait method for structured logging
            $this->logScreenOperation(
                'clear_all_models',
                'success',
                [
                    'model_class' => $modelClass,
                    'model_display_name' => $modelDisplayName,
                    'models_deleted' => $totalModels,
                    'where_conditions' => $whereConditions,
                    'action_type' => 'bulk_delete_all'
                ]
            );

            Toast::success("Successfully cleared all {$totalModels} {$modelDisplayName} from the database.");

        } catch (\Exception $e) {
            $this->logScreenOperation(
                'clear_all_models',
                'error',
                [
                    'model_class' => $modelClass,
                    'model_display_name' => $modelDisplayName,
                    'where_conditions' => $whereConditions,
                    'error' => $e->getMessage(),
                    'action_type' => 'bulk_delete_all'
                ],
                'error'
            );
            
            Toast::error("Failed to clear {$modelDisplayName}: " . $e->getMessage());
        }

        return redirect()->back();
    }

    // ========================================================================================
    // SETTINGS PERSISTENCE METHODS
    // ========================================================================================

    /**
     * Save settings to the database
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveSettings(Request $request)
    {
        $settings = $request->input('settings', []);
        $count = 0;
        
        if ($settings) {
            // Collect only entries that have actually changed
            $changedSettings = [];
            
            foreach ($settings as $key => $value) {
                // Convert flat input names back to database keys (e.g., mail_from__address -> mail_from.address)
                $dbKey = $this->convertFlatInputToDbKey($key);
                
                // Special handling for nested LDAP, OIDC and Mail settings that come as JSON objects
                if (is_array($value) && (str_starts_with($dbKey, 'ldap_') || str_starts_with($dbKey, 'open_id_connect_') || str_starts_with($dbKey, 'mail_'))) {
                    // Handle nested settings - flatten them with dot notation
                    $this->processNestedSettings($dbKey, $value, $changedSettings);
                    continue; // Skip the main key processing since we handled the nested ones
                }
                
                // Only save password fields if they are not empty
                if ((str_contains($dbKey, 'bind_pw') || str_contains($dbKey, 'password') || str_contains($dbKey, 'secret')) && empty($value)) {
                    continue;
                }
                
                // Since the keys are already stored with underscores in the database, we can search directly
                $setting = AppSetting::where('key', $dbKey)->first();
                
                if ($setting) {
                    // Transform the value based on its type for proper comparison
                    $normalizedNewValue = $this->normalizeValueForComparison($value, $setting->type);
                    $normalizedExistingValue = $this->normalizeValueForComparison($setting->value, $setting->type);
                    
                    // Comparison of normalized values for change detection
                    if ($normalizedExistingValue !== $normalizedNewValue) {
                        $changedSettings[] = [
                            'key' => $dbKey,
                            'value' => $normalizedNewValue,
                            'type' => $setting->type,
                            'model' => $setting
                        ];
                        Log::info("Setting changed: {$dbKey} from '" . json_encode($normalizedExistingValue) . "' to '" . json_encode($normalizedNewValue) . "'");
                    }
                } else {
                    Log::warning("Setting not found: {$dbKey}");
                    Toast::warning("Setting not found: {$dbKey}");
                }
            }
            
            // Perform updates only for changed settings
            foreach ($changedSettings as $changed) {
                $setting = $changed['model'];
                $formattedValue = $this->formatValueForDatabaseStorage($changed['value'], $changed['type']);
                
                // Update the setting
                $setting->value = $formattedValue;
                $setting->save();
                
                // Cache für diese Einstellung löschen
                Cache::forget('app_settings_' . $setting->key);
                
                // Also clear the config cache for this source, if it exists
                if ($setting->source) {
                    Cache::forget('config_' . $setting->source);
                }
                
                $count++;
            }
            
            // User feedback based on the changes
            if ($count > 0) {
                // Clear the entire configuration cache
                try {
                    Artisan::call('config:clear');
                    
                    // Clear the specific config override cache
                    \App\Providers\ConfigServiceProvider::clearConfigCache();
                    
                    Toast::success("{$count} system settings have been updated, and the configuration cache has been cleared.");
                } catch (\Exception $e) {
                    Toast::warning("Settings saved, but clearing cache failed: " . $e->getMessage());
                }
            } else {
                Toast::info("No changes detected");
            }
        }
        
        return;
    }

    /**
     * Process nested settings (like LDAP and OIDC settings) that come as JSON objects
     * but are stored in the database with dot notation
     *
     * @param string $parentKey The parent key (e.g., 'ldap_cache', 'ldap_custom_connection')
     * @param array $nestedData The nested data array
     * @param array &$changedSettings Reference to the changed settings array
     */
    private function processNestedSettings(string $parentKey, array $nestedData, array &$changedSettings)
    {
        foreach ($nestedData as $nestedKey => $nestedValue) {
            // Create the dot notation key
            $dotNotationKey = $parentKey . '.' . $nestedKey;
            
            // Handle deeply nested arrays (like attribute_map)
            if (is_array($nestedValue)) {
                $this->processNestedSettings($dotNotationKey, $nestedValue, $changedSettings);
                continue;
            }
            
            // Skip empty password fields
            if ((str_contains($dotNotationKey, 'bind_pw') || str_contains($dotNotationKey, 'password') || str_contains($dotNotationKey, 'secret')) && empty($nestedValue)) {
                continue;
            }
            
            // Look for the setting in the database
            $setting = AppSetting::where('key', $dotNotationKey)->first();
            
            if ($setting) {
                // Process this nested setting
                $normalizedNewValue = $this->normalizeValueForComparison($nestedValue, $setting->type);
                $normalizedExistingValue = $this->normalizeValueForComparison($setting->value, $setting->type);
                
                if ($normalizedExistingValue !== $normalizedNewValue) {
                    $changedSettings[] = [
                        'key' => $dotNotationKey,
                        'value' => $normalizedNewValue,
                        'type' => $setting->type,
                        'model' => $setting
                    ];
                    Log::info("Nested setting changed: {$dotNotationKey} from '" . json_encode($normalizedExistingValue) . "' to '" . json_encode($normalizedNewValue) . "'");
                }
            } else {
                Log::warning("Nested setting not found: {$dotNotationKey}");
                Toast::warning("Nested setting not found: {$dotNotationKey}");
            }
        }
    }

    // ========================================================================================
    // VALUE NORMALIZATION AND FORMATTING METHODS
    // ========================================================================================

    /**
     * Normalize a value for comparison based on its type
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    private function normalizeValueForComparison($value, string $type)
    {
        switch ($type) {
            case 'boolean':
                // Convert to a boolean value for consistent comparison
                if (is_string($value)) {
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                return (bool) $value;
                
            case 'integer':
                // Convert to an integer for consistent comparison
                return (int) $value;
                
            case 'json':
                // Decode JSON strings into arrays/objects for structural comparison
                if (is_string($value)) {
                    try {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            return $decoded;
                        }
                    } catch (\Exception $e) {
                        // In case of an error, return the original value
                    }
                }
                // If it's already an array or decoding fails
                return $value;
                
            default:
                // Return as string for strings and other types
                return (string) $value;
        }
    }

    /**
     * Format a value for database storage based on its type
     *
     * @param mixed $value
     * @param string $type
     * @return string
     */
    private function formatValueForDatabaseStorage($value, string $type): string
    {
        switch ($type) {
            case 'boolean':
                // Store as 'true' or 'false' string
                return (filter_var($value, FILTER_VALIDATE_BOOLEAN)) ? 'true' : 'false';
                
            case 'integer':
                // Convert to string for storage
                return (string) (int) $value;
                
            case 'json':
                // Convert array/object to JSON string
                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                } elseif (is_string($value)) {
                    try {
                        // Attempt to decode and re-encode to ensure a consistent format
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            return json_encode($decoded);
                        }
                    } catch (\Exception $e) {
                        // In case of an error: Return the original value if it is a valid JSON string
                    }
                }
                return $value;
                
            default:
                // Return as string for all other types
                return (string) $value;
        }
    }

    // ========================================================================================
    // KEY CONVERSION METHODS
    // ========================================================================================

    /**
     * Convert database key format to Laravel config key format
     * Only replaces the first underscore with a dot
     * 
     * @param string $dbKey Database key with underscore notation (e.g., 'app_name')
     * @return string Config key in dot notation (e.g., 'app.name')
     */
    protected function convertDbKeyToConfigKey(string $dbKey): string
    {
        // Finde die Position des ersten Unterstriches
        $pos = strpos($dbKey, '_');
        
        if ($pos === false) {
            // Falls kein Unterstrich vorhanden ist, gib den Key unverändert zurück
            return $dbKey;
        }
        
        // Trenne den Key in Konfigurationsdatei und eigentlichen Schlüssel
        $configFile = substr($dbKey, 0, $pos);
        $realKey = substr($dbKey, $pos + 1);
        
        // Erstelle den Config-Key im Format "configFile.realKey"
        return $configFile . '.' . $realKey;
    }

    /**
     * Convert a database key with dots to a flat input name
     * This prevents the browser from creating nested objects
     * 
     * @param string $dbKey Database key like 'mail_from.address'
     * @return string Input name like 'settings[mail_from__address]'
     */
    protected function convertDbKeyToFlatInputName(string $dbKey): string
    {
        // Replace dots with double underscores to flatten the structure
        $flatKey = str_replace('.', '__', $dbKey);
        return "settings.{$flatKey}";
    }

    /**
     * Convert a flat input name back to database key
     * 
     * @param string $inputName Input name like 'mail_from__address'
     * @return string Database key like 'mail_from.address'
     */
    protected function convertFlatInputToDbKey(string $inputName): string
    {
        // Replace double underscores back to dots
        return str_replace('__', '.', $inputName);
    }

    // ========================================================================================
    // FORM FIELD GENERATION METHODS
    // ========================================================================================

    /**
     * Create the appropriate form field based on setting type
     * This method handles all common field types and special cases for settings forms
     *
     * @param AppSetting $setting
     * @return \Orchid\Screen\Field|\Orchid\Screen\Fields\Group
     */
    protected function generateFieldForSetting(AppSetting $setting)
    {
        $key = $setting->key;
        
        // Handle special display overrides for certain keys
        if ($key === 'ldap_connections_default_hosts' || $key === 'open_id_connect_oidc_scopes') {
            $setting->type = 'string';
        }
        
        // Generate the correct Laravel config name for display
        $displayKey = $this->convertDbKeyToConfigKey($key);
        
        // Use flat input name to prevent nested objects in frontend
        $inputName = $this->convertDbKeyToFlatInputName($key);

        return $this->createFieldByType($setting, $key, $displayKey, $inputName);
    }

    /**
     * Create form field based on setting type with special handling for various input types
     *
     * @param AppSetting $setting
     * @param string $key
     * @param string $displayKey
     * @param string $inputName
     * @return \Orchid\Screen\Field|\Orchid\Screen\Fields\Group
     */
    private function createFieldByType(AppSetting $setting, string $key, string $displayKey, string $inputName)
    {
        switch ($setting->type) {
            case 'boolean':
                return \Orchid\Screen\Fields\Group::make([
                    \Orchid\Screen\Fields\Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    \Orchid\Screen\Fields\Switcher::make($inputName)
                        ->sendTrueOrFalse()
                        ->value($setting->typed_value),
                ])
                ->alignCenter()
                ->widthColumns('1fr max-content');
                
            case 'integer':
                return \Orchid\Screen\Fields\Group::make([
                    \Orchid\Screen\Fields\Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    \Orchid\Screen\Fields\Input::make($inputName)
                        ->type('number')
                        ->value($setting->value)
                        ->horizontal(),
                ])
                ->widthColumns('1fr 1fr');
                
            case 'json':
                return \Orchid\Screen\Fields\Group::make([
                    \Orchid\Screen\Fields\Label::make("label_{$key}")
                        ->title($setting->description)
                        ->help($displayKey)
                        ->addclass('fw-bold'),
                    \Orchid\Screen\Fields\TextArea::make($inputName)
                        ->rows(10)
                        ->value(json_encode($setting->typed_value, JSON_PRETTY_PRINT))
                        ->style('min-width: 100%; resize: vertical;'),  
                ])
                ->widthColumns('1fr 1fr');
            
            case 'string':
            default:
                return $this->createStringFieldWithSpecialHandling($setting, $key, $displayKey, $inputName);
        }
    }

    /**
     * Create string input fields with special handling for specific field types
     *
     * @param AppSetting $setting
     * @param string $key
     * @param string $displayKey
     * @param string $inputName
     * @return \Orchid\Screen\Field|\Orchid\Screen\Fields\Group
     */
    private function createStringFieldWithSpecialHandling(AppSetting $setting, string $key, string $displayKey, string $inputName)
    {
        // Special handling for authentication method dropdown
        if ($key === 'auth_authentication_method') {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Select::make($inputName)
                    ->options([
                        'LDAP' => 'LDAP',
                        'OIDC' => 'OpenID Connect',
                        'Shibboleth' => 'Shibboleth',
                    ])
                    ->value($setting->value),
            ])
            ->alignCenter()
            ->widthColumns('1fr 1fr');
        }

        // Special handling for passkey method dropdown
        if ($key === 'auth_passkey_method') {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Select::make($inputName)
                    ->options([
                        'user' => 'User (Manual passkey entry)',
                        'auto' => 'Auto (Automatic passkey generation)',
                    ])
                    ->value($setting->value),
            ])
            ->alignCenter()
            ->widthColumns('1fr 1fr');
        }

        // Special handling for passkey secret dropdown
        if ($key === 'auth_passkey_secret') {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey),
                \Orchid\Screen\Fields\Select::make($inputName)
                    ->options([
                        'username' => 'Username',
                        'time' => 'Time of registration',
                        'mixed' => 'Name and Time of registration',
                        'publicKey' => 'Public Key',
                        'default' => 'Default',
                    ])
                    ->value($setting->value),
            ])
            ->alignCenter()
            ->widthColumns('1fr 1fr');
        }

        // Special handling for mail driver selection
        if ($key === 'mail_default' || $key === 'mail_mailer') {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Select::make($inputName)
                    ->options([
                        'smtp' => 'SMTP',
                        'herd' => 'Herd (Local Development)',
                        'sendmail' => 'Sendmail',
                        'log' => 'Log (for testing)',
                        'array' => 'Array (for testing)',
                        'mailgun' => 'Mailgun',
                        'ses' => 'Amazon SES',
                        'postmark' => 'Postmark',
                    ])
                    ->value($setting->value),
            ])
            ->alignCenter()
            ->widthColumns('1fr 1fr');
        }

        // Special handling for logging default channel
        if ($key === 'logging_default') {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Select::make($inputName)
                    ->options([
                        'stack' => 'Stack (Multiple channels)',
                        'single' => 'Single File',
                        'daily' => 'Daily Rotating Files',
                        'syslog' => 'System Log',
                        'database' => 'Database',
                        'errorlog' => 'PHP Error Log',
                        'null' => 'Null (Disable logging)',
                        'emergency' => 'Emergency (Write to error log)',
                    ])
                    ->value($setting->value),
            ])
            ->alignCenter()
            ->widthColumns('1fr 1fr');
        }

        // Special handling for log levels
        if (str_contains($key, '_level') && (str_contains($key, 'logging_') || str_contains($key, 'log_'))) {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Select::make($inputName)
                    ->options([
                        'debug' => 'Debug',
                        'info' => 'Info',
                        'notice' => 'Notice',
                        'warning' => 'Warning',
                        'error' => 'Error',
                        'critical' => 'Critical',
                        'alert' => 'Alert',
                        'emergency' => 'Emergency',
                    ])
                    ->value($setting->value),
            ])
            ->alignCenter()
            ->widthColumns('1fr 1fr');
        }

        // Special handling for encryption
        if (str_contains($key, 'encryption')) {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Select::make($inputName)
                    ->options([
                        '' => 'None',
                        'tls' => 'TLS',
                        'ssl' => 'SSL',
                    ])
                    ->value($setting->value)
                    ->empty('None'),
            ])
            ->alignCenter()
            ->widthColumns('1fr 1fr');
        }

        // Special handling for transport type
        if (str_contains($key, 'transport')) {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Select::make($inputName)
                    ->options([
                        'smtp' => 'SMTP',
                        'sendmail' => 'Sendmail',
                        'log' => 'Log',
                    ])
                    ->value($setting->value)
                    ->readonly(), // Transport type should match the mailer name
            ])
            ->alignCenter()
            ->widthColumns('1fr 1fr');
        }

        // Special handling for password fields
        if (str_contains($key, 'bind_pw') || str_contains($key, 'password') || str_contains($key, 'secret')) {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Input::make($inputName)
                    ->type('password')
                    ->value(''),
            ])
            ->alignCenter()
            ->widthColumns('1fr 1fr');
        }

        // Default string input field
        return \Orchid\Screen\Fields\Group::make([
            \Orchid\Screen\Fields\Label::make("label_{$key}")
                ->title($setting->description)
                ->help($displayKey)
                ->addclass('fw-bold'),
            \Orchid\Screen\Fields\Input::make($inputName)
                ->value($setting->value),
        ])
        ->alignCenter()
        ->widthColumns('1fr 1fr');
    }
}
