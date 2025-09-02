<?php

namespace App\Orchid\Layouts\Settings;

use App\Orchid\Traits\OrchidSettingsManagementTrait;
use Orchid\Screen\Layouts\Rows;
use Orchid\Screen\Layout;

class LoggingSettingsLayout extends Rows
{
    use OrchidSettingsManagementTrait;

    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'logging_settings';

    /**
     * Get the fields elements to be displayed.
     *
     * @return \Orchid\Screen\Field[]
     */
    protected function fields(): iterable
    {
        $fields = [];

        foreach ($this->query->get('logging_settings', []) as $setting) {
            $fields[] = $this->generateFieldForSetting($setting);
        }

        return $fields;
    }
}
