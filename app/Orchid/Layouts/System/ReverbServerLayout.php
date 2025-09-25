<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\System;

use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Label;
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
            Group::make([
                Label::make('label_server_host')
                    ->title('Server Host')
                    ->help('config(\'reverb.servers.reverb.host\')')
                    ->addclass('fw-bold'),
                Input::make('settings.reverb_servers__reverb__host')
                    ->placeholder('0.0.0.0'),
            ])
                ->alignCenter()
                ->widthColumns('1fr 1fr'),

            Group::make([
                Label::make('label_server_hostname')
                    ->title('Server Hostname')
                    ->help('config(\'reverb.servers.reverb.hostname\')')
                    ->addclass('fw-bold'),
                Input::make('settings.reverb_servers__reverb__hostname')
                    ->placeholder('hawki.test'),
            ])
                ->alignCenter()
                ->widthColumns('1fr 1fr'),

            Group::make([
                Label::make('label_server_port')
                    ->title('Server Port')
                    ->help('config(\'reverb.servers.reverb.port\')')
                    ->addclass('fw-bold'),
                Input::make('settings.reverb_servers__reverb__port')
                    ->type('text')
                    ->placeholder('8080')
                    ->style('text-align: right;')
                    ->mask([
                        'mask' => '9{1,10}',
                        'numericInput' => true,
                    ]),
            ])
                ->alignCenter()
                ->widthColumns('1fr 1fr'),
        ];
    }
}
