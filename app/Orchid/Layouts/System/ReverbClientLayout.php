<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\System;

use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class ReverbClientLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            Group::make([
                Label::make('label_client_host')
                    ->title('Client Host')
                    ->help('config(\'reverb.apps.apps.0.options.host\')')
                    ->addclass('fw-bold'),
                Input::make('settings.reverb_apps__apps__0__options__host')
                    ->placeholder('hawki.test'),
            ])
                ->alignCenter()
                ->widthColumns('1fr 1fr'),

            Group::make([
                Label::make('label_client_port')
                    ->title('Client Port')
                    ->help('config(\'reverb.apps.apps.0.options.port\')')
                    ->addclass('fw-bold'),
                Input::make('settings.reverb_apps__apps__0__options__port')
                    ->type('text')
                    ->placeholder('443')
                    ->style('text-align: right;')
                    ->mask([
                        'mask' => '9{1,10}',
                        'numericInput' => true,
                    ]),
            ])
                ->alignCenter()
                ->widthColumns('1fr 1fr'),

            Group::make([
                Label::make('label_connection_scheme')
                    ->title('Connection Scheme')
                    ->help('config(\'reverb.apps.apps.0.options.scheme\')')
                    ->addclass('fw-bold'),
                Select::make('settings.reverb_apps__apps__0__options__scheme')
                    ->options([
                        'https' => 'HTTPS (Secure)',
                        'http' => 'HTTP (Insecure)',
                    ]),
            ])
                ->alignCenter()
                ->widthColumns('1fr 1fr'),
        ];
    }
}
