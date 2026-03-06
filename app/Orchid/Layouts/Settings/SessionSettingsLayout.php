<?php

namespace App\Orchid\Layouts\Settings;

use App\Orchid\Traits\OrchidSettingsManagementTrait;
use Orchid\Support\Facades\Layout;

class SessionSettingsLayout
{
    use OrchidSettingsManagementTrait;

    /**
     * Build layout for session settings
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $sessionSettings
     * @return \Orchid\Screen\Layout[]
     */
    public static function build($sessionSettings): array
    {
        $instance = new static;

        $sessionFields = [];

        // Generate fields for all session settings
        foreach ($sessionSettings as $setting) {
            $sessionFields[] = $instance->generateFieldForSetting($setting);
        }

        $layouts = [];

        // Session Settings Block
        if (! empty($sessionFields)) {
            $layouts[] = Layout::block([
                Layout::rows($sessionFields),
            ])
                ->title('Session Settings')
                ->description('Configure session lifetime and security options. Changes take effect immediately for new sessions.');
        }

        return $layouts;
    }
}
