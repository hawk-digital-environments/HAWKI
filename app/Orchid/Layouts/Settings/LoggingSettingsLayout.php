<?php

namespace App\Orchid\Layouts\Settings;

use App\Orchid\Traits\OrchidSettingsManagementTrait;
use Orchid\Support\Facades\Layout;

class LoggingSettingsLayout
{
    use OrchidSettingsManagementTrait;

    /**
     * Build layouts for logging settings, separating general settings from trigger settings
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $loggingSettings
     * @return \Orchid\Screen\Layout[]
     */
    public static function build($loggingSettings): array
    {
        $instance = new static;

        $loggingGeneralSettings = [];
        $loggingTriggerSettings = [];

        // Quick and dirty: Definiere die gewünschte Reihenfolge der Trigger
        $triggerOrder = [
            'logging_triggers.curl_return_object',
            'logging_triggers.normalized_return_object',
            'logging_triggers.formatted_stream_chunk',
            'logging_triggers.translated_return_object',
            'logging_triggers.default_model',
            'logging_triggers.usage',
        ];

        $triggerSettingsMap = [];

        // Sortiere die Logging-Einstellungen nach Typ
        foreach ($loggingSettings as $setting) {
            if (str_starts_with($setting->key, 'logging_triggers.')) {
                $triggerSettingsMap[$setting->key] = $instance->generateFieldForSetting($setting);
            } else {
                $loggingGeneralSettings[] = $instance->generateFieldForSetting($setting);
            }
        }

        // Sortiere Trigger nach der definierten Reihenfolge
        foreach ($triggerOrder as $triggerKey) {
            if (isset($triggerSettingsMap[$triggerKey])) {
                $loggingTriggerSettings[] = $triggerSettingsMap[$triggerKey];
            }
        }

        // Füge alle nicht in der Liste enthaltenen Trigger am Ende hinzu
        foreach ($triggerSettingsMap as $key => $field) {
            if (! in_array($key, $triggerOrder)) {
                $loggingTriggerSettings[] = $field;
            }
        }

        $layouts = [];

        // Layout für allgemeine Logging-Einstellungen
        if (! empty($loggingGeneralSettings)) {
            $layouts[] = Layout::rows($loggingGeneralSettings)
                ->title('General Logging Configuration');
        }

        // Layout für Logging-Trigger-Einstellungen
        if (! empty($loggingTriggerSettings)) {
            $layouts[] = Layout::rows($loggingTriggerSettings)
                ->title('Debug Logging Triggers');
        }

        return $layouts;
    }
}
