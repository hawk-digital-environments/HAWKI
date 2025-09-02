<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\System;

use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Rows;

class ReverbServerLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('settings.reverb_servers__reverb__host')
                ->title('Server Host')
                ->placeholder('0.0.0.0')
                ->help('The host address the Reverb server binds to (0.0.0.0 for all interfaces)'),

            Input::make('settings.reverb_servers__reverb__hostname')
                ->title('Server Hostname')
                ->placeholder('hawki.test')
                ->help('The public hostname for the Reverb server (used for client connections)'),

            Input::make('settings.reverb_servers__reverb__port')
                ->type('number')
                ->title('Server Port')
                ->placeholder('8080')
                ->help('The port the Reverb server listens on (default: 8080)'),
        ];
    }
}
