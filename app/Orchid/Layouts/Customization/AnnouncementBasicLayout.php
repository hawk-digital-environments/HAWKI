<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class AnnouncementBasicLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): iterable
    {
        return [
            Input::make('announcement.title')
                ->title('Identifier')
                ->placeholder('Enter announcement identifier (e.g., first-upload-guide)')
                ->required()
                ->help('Internal identifier for this announcement (View Key will be auto-generated from this)'),

            Select::make('announcement.type')
                ->title('Type')
                ->options([
                    'policy' => 'Policy',
                    'news' => 'News',
                    'system' => 'System',
                    'event' => 'Event',
                    'info' => 'Info',
                ])
                ->required()
                ->help('Type of announcement'),

            CheckBox::make('announcement.is_forced')
                ->title('Forced')
                ->placeholder('Require users to accept this announcement')
                ->sendTrueOrFalse()
                ->help('If enabled, users must accept this announcement before continuing'),
        ];
    }
}
