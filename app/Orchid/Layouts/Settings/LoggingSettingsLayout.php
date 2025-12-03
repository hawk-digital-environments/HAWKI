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

        // Sammle alle Trigger-Settings mit ihrer Nummerierung aus der Description
        $triggerSettingsMap = [];

        // Sortiere die Logging-Einstellungen nach Typ
        foreach ($loggingSettings as $setting) {
            if (str_starts_with($setting->key, 'logging_triggers.')) {
                // Extrahiere Nummerierung aus Description (z.B. "0. Log raw..." -> 0)
                $order = 9999; // Default für nicht-nummerierte Trigger
                if ($setting->description && preg_match('/^(\d+)\./', $setting->description, $matches)) {
                    $order = (int) $matches[1];
                }
                
                $triggerSettingsMap[] = [
                    'key' => $setting->key,
                    'order' => $order,
                    'field' => $instance->generateFieldForSetting($setting)
                ];
            } else {
                $loggingGeneralSettings[] = $instance->generateFieldForSetting($setting);
            }
        }

        // Sortiere Trigger nach Nummerierung (order) und dann alphabetisch nach key
        usort($triggerSettingsMap, function($a, $b) {
            if ($a['order'] === $b['order']) {
                return strcmp($a['key'], $b['key']);
            }
            return $a['order'] <=> $b['order'];
        });

        // Extrahiere die sortierten Fields
        $loggingTriggerSettings = array_map(function($item) {
            return $item['field'];
        }, $triggerSettingsMap);

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
