<?php

namespace App\Orchid\Traits;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LocalizationController;
use App\Models\AppLocalizedText;
use App\Models\AppSetting;
use App\Models\AppSystemText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Orchid\Support\Facades\Toast;

trait OrchidSettingsManagementTrait
{
    /**
     * Save model with detailed change detection and audit logging.
     * Logs which attributes have changed and new/old values
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return array Returns ['hasChanges' => bool, 'changedFields' => array, 'savedModel' => Model]
     */
    protected function saveModelWithChangeDetection($model, array $attributes, string $actionDescription, ?array $originalValues = null): array
    {
        if ($originalValues === null) {
            $originalValues = $model->getOriginal();
        }

        // Fill the model with new attributes
        $model->fill($attributes);

        // Check what has actually changed
        $dirtyAttributes = $model->getDirty();
        $hasChanges = ! empty($dirtyAttributes);

        if ($hasChanges) {
            $changedFields = [];
            foreach ($dirtyAttributes as $field => $newValue) {
                $oldValue = $originalValues[$field] ?? null;
                $changedFields[] = [
                    'field' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ];
            }

            // Save the model
            $model->save();

            // Log the changes with context
            Log::info($actionDescription, [
                'model' => get_class($model),
                'model_id' => $model->getKey(),
                'changed_fields' => $changedFields,
                'user_id' => auth()->id(),
                'action_type' => 'model_update_with_audit',
            ]);

            return ['hasChanges' => true, 'changedFields' => $changedFields, 'savedModel' => $model];
        }

        return ['hasChanges' => false, 'changedFields' => [], 'savedModel' => $model];
    }

    /**
     * Log batch operations with detailed information about what was changed.
     */
    protected function logBatchOperation(string $operation, string $entityType, array $summary)
    {
        Log::info("Batch operation: {$operation}", [
            'entity_type' => $entityType,
            'operation' => $operation,
            'summary' => $summary,
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
            'action_type' => 'batch_operation',
        ]);
    }

    // ========================================================================================
    // SETTINGS MANAGEMENT METHODS
    // ========================================================================================

    /**
     * Save system settings with fine-grained change detection and batch logging.
     * Only updates settings that have actually changed from their current database values.
     */
    public function saveSettings(Request $request)
    {
        $requestSettings = $request->get('settings', []);
        $changedSettings = [];
        $count = 0;

        if ($requestSettings) {
            foreach ($requestSettings as $flatKey => $value) {
                // Convert flat input name back to database key
                $dbKey = $this->convertFlatInputToDbKey($flatKey);

                // Handle nested settings (JSON objects in the form)
                if (is_array($value)) {
                    $this->processNestedSettings($dbKey, $value, $changedSettings);

                    continue;
                }

                // Skip empty password fields
                if ((str_contains($dbKey, 'bind_pw') || str_contains($dbKey, 'password') || str_contains($dbKey, 'secret')) && empty($value)) {
                    continue;
                }

                // Look for the setting in the database
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
                            'model' => $setting,
                        ];
                        Log::info("Setting changed: {$dbKey} from '".json_encode($normalizedExistingValue)."' to '".json_encode($normalizedNewValue)."'");
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
                Cache::forget('app_settings_'.$setting->key);

                // Also clear the config cache for this source, if it exists
                if ($setting->source) {
                    Cache::forget('config_'.$setting->source);
                }

                $count++;
            }

            // User feedback based on the changes
            if ($count > 0) {
                // Clear the entire configuration cache
                try {
                    Artisan::call('config:clear');

                    // Clear any relevant caches
                    Cache::flush();

                    Toast::success("{$count} system settings have been updated, and the configuration cache has been cleared.");
                } catch (\Exception $e) {
                    Toast::warning('Settings saved, but clearing cache failed: '.$e->getMessage());
                }
            } else {
                Toast::info('No changes detected');
            }
        }

    }

    /**
     * Process nested settings (like LDAP and OIDC settings) that come as JSON objects
     * but are stored in the database with dot notation
     *
     * @param  string  $parentKey  The parent key (e.g., 'ldap_cache', 'ldap_custom_connection')
     * @param  array  $nestedData  The nested data array
     * @param  array  &$changedSettings  Reference to the changed settings array
     */
    private function processNestedSettings(string $parentKey, array $nestedData, array &$changedSettings)
    {
        foreach ($nestedData as $nestedKey => $nestedValue) {
            // Create the dot notation key
            $dotNotationKey = $parentKey.'.'.$nestedKey;

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
                        'model' => $setting,
                    ];
                    Log::info("Nested setting changed: {$dotNotationKey} from '".json_encode($normalizedExistingValue)."' to '".json_encode($normalizedNewValue)."'");
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
     * @param  mixed  $value
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
     * @param  mixed  $value
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
     * @param  string  $dbKey  Database key with underscore notation (e.g., 'app_name')
     * @return string Config key in dot notation (e.g., 'app.name')
     */
    protected function convertDbKeyToConfigKey(string $dbKey): string
    {
        // Finde die Position des ersten Unterstriches
        $pos = strpos($dbKey, '_');

        if ($pos === false) {
            // Falls kein Unterstrich vorhanden ist, gib den Key unverändert zurück
            return "config('{$dbKey}')";
        }

        // Trenne den Key in Konfigurationsdatei und eigentlichen Schlüssel
        $configFile = substr($dbKey, 0, $pos);
        $realKey = substr($dbKey, $pos + 1);

        // Erstelle den Config-Key im Format "config('configFile.realKey')"
        return "config('{$configFile}.{$realKey}')";
    }

    /**
     * Convert a database key with dots to a flat input name
     * This prevents the browser from creating nested objects
     *
     * @param  string  $dbKey  Database key like 'mail_from.address'
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
     * @param  string  $inputName  Input name like 'mail_from__address'
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
                        ->type('text')
                        ->value($setting->value)
                        ->style('text-align: right;')
                        ->mask([
                            'mask' => '9{1,10}',
                            'numericInput' => true,
                        ]),
                ])
                    ->alignCenter()
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
                    ->widthColumns('1fr max-content');

            case 'string':
            default:
                return $this->createStringFieldWithSpecialHandling($setting, $key, $displayKey, $inputName);
        }
    }

    /**
     * Create string input fields with special handling for specific field types
     *
     * @return \Orchid\Screen\Field|\Orchid\Screen\Fields\Group
     */
    private function createStringFieldWithSpecialHandling(AppSetting $setting, string $key, string $displayKey, string $inputName)
    {
        // Special handling for locale dropdown
        if ($key === 'app_locale') {
            $locales = config('locale.langs', []);
            $options = [];

            foreach ($locales as $localeKey => $localeData) {
                if ($localeData['active'] ?? false) {
                    $options[$localeKey] = $localeData['name'].' ('.$localeKey.')';
                }
            }

            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Select::make($inputName)
                    ->options($options)
                    ->value($setting->value),
            ])
                ->alignCenter()
                ->widthColumns('1fr 1fr');
        }

        // Special handling for authentication method dropdown
        if ($key === 'auth_authentication_method') {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Select::make($inputName)
                    ->options([
                        'LOCAL_ONLY' => 'Local Only',
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
                        'user' => 'User',
                        'auto' => 'System',
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

        // Special handling for WebSocket scheme (HTTP/HTTPS)
        if (str_contains($key, 'scheme') && str_contains($key, 'reverb')) {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Select::make($inputName)
                    ->options([
                        'https' => 'HTTPS (Secure)',
                        'http' => 'HTTP (Insecure)',
                    ])
                    ->value($setting->value),
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

        // Special handling for username fields (prevent autofill)
        if (str_contains($key, 'username') || str_contains($key, 'user_name')) {
            return \Orchid\Screen\Fields\Group::make([
                \Orchid\Screen\Fields\Label::make("label_{$key}")
                    ->title($setting->description)
                    ->help($displayKey)
                    ->addclass('fw-bold'),
                \Orchid\Screen\Fields\Input::make($inputName)
                    ->value($setting->value)
                    ->set('autocomplete', 'off')
                    ->set('data-lpignore', 'true')
                    ->set('data-form-type', 'other'),
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
                    ->value('')
                    ->set('autocomplete', 'new-password')
                    ->set('data-lpignore', 'true')
                    ->set('data-form-type', 'other'),
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

    // ========================================================================================
    // TEXT MANAGEMENT METHODS
    // ========================================================================================

    /**
     * Save changes to localized texts with detailed batch logging.
     */
    public function saveLocalizedTexts(Request $request)
    {
        $texts = $request->get('texts', []);
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $createdKeys = [];
        $updatedKeys = [];

        if ($texts) {
            foreach ($texts as $key => $languages) {
                foreach ($languages as $language => $content) {
                    if (! empty($content)) {
                        // Find or create the localized text model
                        $model = AppLocalizedText::firstOrNew([
                            'content_key' => $key,
                            'language' => $language,
                        ]);

                        $isNew = ! $model->exists;
                        $originalContent = $model->content ?? '';

                        // Normalize content for comparison
                        $normalizedNewContent = $this->normalizeContent($content);
                        $normalizedExistingContent = $originalContent ? $this->normalizeContent($originalContent) : '';

                        // Check if content has changed
                        if ($normalizedNewContent !== $normalizedExistingContent) {
                            $model->content = $content;
                            $model->save();

                            $keyInfo = "{$key} ({$language})";
                            if ($isNew) {
                                $created++;
                                $createdKeys[] = $keyInfo;
                            } else {
                                $updated++;
                                $updatedKeys[] = $keyInfo;
                            }
                        } else {
                            $unchanged++;
                        }
                    }
                }
            }

            // Log batch operation
            if ($created > 0 || $updated > 0) {
                $this->logBatchOperation(
                    'save_localized_texts',
                    'localized_texts',
                    [
                        'created' => $created,
                        'updated' => $updated,
                        'unchanged' => $unchanged,
                        'created_keys' => $createdKeys,
                        'updated_keys' => $updatedKeys,
                    ]
                );
            }

            // Provide consolidated feedback
            $totalChanges = $created + $updated;
            if ($totalChanges > 0) {
                Toast::success("$totalChanges localized text entries have been updated ($created created, $updated updated)");

                // Clear text caches to ensure changes are immediately visible
                $this->clearTextCache();
            } else {
                Toast::info('No changes detected in localized texts');
            }
        }

    }

    /**
     * Save system text changes with detailed batch logging.
     */
    public function saveSystemTexts(Request $request)
    {
        $texts = $request->get('system_texts', []);
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $createdKeys = [];
        $updatedKeys = [];

        if ($texts) {
            foreach ($texts as $key => $languages) {
                foreach ($languages as $language => $content) {
                    if (! empty($content)) {
                        // Find or create the system text model
                        $model = AppSystemText::firstOrNew([
                            'content_key' => $key,
                            'language' => $language,
                        ]);

                        $isNew = ! $model->exists;
                        $originalContent = $model->content ?? '';

                        // Normalize content for comparison
                        $normalizedNewContent = $this->normalizeContent($content);
                        $normalizedExistingContent = $originalContent ? $this->normalizeContent($originalContent) : '';

                        // Check if content has changed
                        if ($normalizedNewContent !== $normalizedExistingContent) {
                            $model->content = $content;
                            $model->save();

                            $keyInfo = "{$key} ({$language})";
                            if ($isNew) {
                                $created++;
                                $createdKeys[] = $keyInfo;
                            } else {
                                $updated++;
                                $updatedKeys[] = $keyInfo;
                            }
                        } else {
                            $unchanged++;
                        }
                    }
                }
            }

            // Log batch operation
            if ($created > 0 || $updated > 0) {
                $this->logBatchOperation(
                    'save_system_texts',
                    'system_texts',
                    [
                        'created' => $created,
                        'updated' => $updated,
                        'unchanged' => $unchanged,
                        'created_keys' => $createdKeys,
                        'updated_keys' => $updatedKeys,
                    ]
                );
            }

            // Provide consolidated feedback
            $totalChanges = $created + $updated;
            if ($totalChanges > 0) {
                Toast::success("$totalChanges system text entries have been updated ($created created, $updated updated)");

                // Clear text caches to ensure changes are immediately visible
                $this->clearTextCache();
            } else {
                Toast::info('No changes detected in system texts');
            }
        }

    }

    /**
     * Add a new system text with validation and logging.
     */
    public function addNewSystemText(Request $request)
    {
        $key = $request->get('new_system_key');
        $deText = $request->get('new_system_de_DE');
        $enText = $request->get('new_system_en_US');

        if (empty($key) || empty($deText) || empty($enText)) {
            Toast::error('Key and both language texts are required');

            return redirect()->route('platform.customization.system-text');
        }

        $totalChanges = 0;

        // Create entries for both languages using trait method
        foreach (['de_DE' => $deText, 'en_US' => $enText] as $language => $content) {
            $model = AppSystemText::firstOrNew([
                'content_key' => $key,
                'language' => $language,
            ]);

            $originalValues = $model->getOriginal();

            $result = $this->saveModelWithChangeDetection(
                $model,
                ['content' => $content],
                "New System Text: {$key} ({$language})",
                $originalValues
            );

            if ($result['hasChanges']) {
                $totalChanges++;
            }
        }

        if ($totalChanges > 0) {
            Toast::success("System text '$key' has been created with both language versions.");

            // Clear text caches to ensure changes are immediately visible
            $this->clearTextCache();
        } else {
            Toast::info("System text '$key' already exists with the same values.");
        }

        return redirect()->route('platform.customization.system-text');
    }

    /**
     * Add a new localized text for all supported languages.
     */
    public function addNewLocalizedText(Request $request)
    {
        $key = $request->get('new_key');
        $deContent = $request->get('new_content_de_DE');
        $enContent = $request->get('new_content_en_US');

        if (empty($key) || empty($deContent) || empty($enContent)) {
            Toast::error('Content key and both language contents are required');

            return redirect()->route('platform.customization.localizedtexts');
        }

        // Standardize key (snake_case)
        $key = Str::snake($key);

        $totalChanges = 0;

        // Create entries for both languages using trait method
        foreach (['de_DE' => $deContent, 'en_US' => $enContent] as $language => $content) {
            $model = AppLocalizedText::firstOrNew([
                'content_key' => $key,
                'language' => $language,
            ]);

            $originalValues = $model->getOriginal();

            $result = $this->saveModelWithChangeDetection(
                $model,
                ['content' => $content],
                "New Localized Text: {$key} ({$language})",
                $originalValues
            );

            if ($result['hasChanges']) {
                $totalChanges++;
            }
        }

        if ($totalChanges > 0) {
            Toast::success("Content key '$key' has been created with both language versions.");

            // Clear text caches to ensure changes are immediately visible
            $this->clearTextCache();
        } else {
            Toast::info("Content key '$key' already exists with the same values.");
        }

        return redirect()->route('platform.customization.localizedtexts');
    }

    /**
     * Reset a localized text to its default value from the HTML file.
     */
    public function resetLocalizedText(Request $request)
    {
        $key = $request->get('key');

        if (! $key) {
            Toast::error('No key provided');

            return redirect()->route('platform.customization.localizedtexts');
        }

        try {
            // Convert the key to the expected file prefix format (snake_case to StudlyCase)
            $filePrefix = Str::studly($key);

            // Supported languages
            $supportedLanguages = ['de_DE', 'en_US'];
            $count = 0;
            $resetKeys = [];

            // For each language, try to read the original HTML file
            foreach ($supportedLanguages as $language) {
                $filePath = resource_path("language/{$filePrefix}_{$language}.html");

                if (File::exists($filePath)) {
                    // Read the content from the original file
                    $content = File::get($filePath);

                    if (! empty($content)) {
                        // Update the database with the original content
                        AppLocalizedText::setContent($key, $language, $content);
                        $count++;
                        $resetKeys[] = "{$key} ({$language})";
                    } else {
                        Log::warning("Original file {$filePath} is empty");
                    }
                } else {
                    Log::warning("Original file {$filePath} not found. Cannot reset to default.");
                }
            }

            // Log batch operation if any resets occurred
            if ($count > 0) {
                $this->logBatchOperation(
                    'reset_localized_text',
                    'localized_texts',
                    [
                        'reset_count' => $count,
                        'reset_keys' => $resetKeys,
                        'reset_key' => $key,
                    ]
                );

                Toast::success("Localized text '{$key}' was successfully reset to default value");

                // Clear text caches to ensure changes are immediately visible
                $this->clearTextCache();
            } else {
                Toast::error("Could not find default values for '{$key}'");
            }
        } catch (\Exception $e) {
            Log::error("Error resetting content for key {$key}: ".$e->getMessage());
            Toast::error('Error resetting content: '.$e->getMessage());
        }

    }

    /**
     * Reset a system text to its default value from the JSON file.
     */
    public function resetSystemText(Request $request)
    {
        $key = $request->get('key');

        if (! $key) {
            Toast::error('No key provided');

            return;
        }

        try {
            // Supported languages
            $supportedLanguages = ['de_DE', 'en_US'];
            $count = 0;
            $resetKeys = [];

            // For each language, try to read the original JSON file
            foreach ($supportedLanguages as $language) {
                $jsonFile = resource_path("language/{$language}.json");

                if (File::exists($jsonFile)) {
                    // Read the content from the JSON file
                    $jsonContent = File::get($jsonFile);
                    $textData = json_decode($jsonContent, true);

                    // Find the key in the JSON data
                    if (isset($textData[$key])) {
                        $content = $textData[$key];

                        if (! empty($content)) {
                            // Update the database with the original content
                            AppSystemText::setText($key, $language, $content);
                            $count++;
                            $resetKeys[] = "{$key} ({$language})";
                        } else {
                            Log::warning("Value for key '{$key}' in {$jsonFile} is empty");
                        }
                    } else {
                        Log::warning("Key '{$key}' not found in {$jsonFile}");
                    }
                } else {
                    Log::warning("JSON file {$jsonFile} not found. Cannot reset system text.");
                }
            }

            // Log batch operation if any resets occurred
            if ($count > 0) {
                $this->logBatchOperation(
                    'reset_system_text',
                    'system_texts',
                    [
                        'reset_count' => $count,
                        'reset_keys' => $resetKeys,
                        'reset_key' => $key,
                    ]
                );

                Toast::success("System text '{$key}' was successfully reset to default value");

                // Clear text caches to ensure changes are immediately visible
                $this->clearTextCache();
            } else {
                Toast::error("Could not find default values for '{$key}'");
            }
        } catch (\Exception $e) {
            Log::error("Error resetting system text for key {$key}: ".$e->getMessage());
            Toast::error('Error resetting system text: '.$e->getMessage());
        }

    }

    // ========================================================================================
    // TEXT UTILITY METHODS
    // ========================================================================================

    /**
     * Normalize content for reliable comparison
     */
    protected function normalizeContent(string $content): string
    {
        // Trim whitespace and normalize line endings
        $normalized = trim($content);
        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);

        // Normalize common HTML entities
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5);

        return $normalized;
    }

    /**
     * Clear the text cache.
     */
    public function clearTextCache()
    {
        try {
            // Clear system texts cache in LanguageController
            LanguageController::clearCaches();

            // Clear localized texts cache in LocalizationController
            LocalizationController::clearCaches();

            Toast::success('Translation cache has been cleared successfully.');
        } catch (\Exception $e) {
            Log::error('Error clearing translation cache: '.$e->getMessage());
            Toast::error('Error clearing cache: '.$e->getMessage());
        }

    }
}
