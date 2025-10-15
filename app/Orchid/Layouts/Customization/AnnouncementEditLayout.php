<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\SimpleMDE;
use Orchid\Screen\Layouts\Rows;

class AnnouncementEditLayout extends Rows
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
                ->title('Title')
                ->placeholder('Enter announcement title')
                ->required()
                ->help('Title of the announcement'),

            Input::make('announcement.view')
                ->title('View Key')
                ->placeholder('e.g., basic-guidelines')
                ->required()
                ->help('Unique identifier for this announcement (used in code)'),

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
                ->help('If enabled, users must accept this announcement'),

            CheckBox::make('announcement.is_global')
                ->title('Global')
                ->placeholder('Show to all users')
                ->sendTrueOrFalse()
                ->help('If enabled, all users will see this announcement'),

            Input::make('announcement.anchor')
                ->title('Anchor')
                ->placeholder('Optional anchor')
                ->help('Optional anchor for specific page placement'),

            DateTimer::make('announcement.starts_at')
                ->title('Start Date')
                ->placeholder('Leave empty to start immediately')
                ->allowEmpty()
                ->format('Y-m-d H:i:s')
                ->help('When the announcement should become active'),

            DateTimer::make('announcement.expires_at')
                ->title('Expiration Date')
                ->placeholder('Leave empty for no expiration')
                ->allowEmpty()
                ->format('Y-m-d H:i:s')
                ->help('When the announcement should expire'),

            SimpleMDE::make('de_content')
                ->title('German Content (de_DE)')
                ->help('Markdown content for German language')
                ->placeholder('# Überschrift

Content in Markdown format...'),

            SimpleMDE::make('en_content')
                ->title('English Content (en_US)')
                ->help('Markdown content for English language')
                ->placeholder('# Heading

Content in Markdown format...'),
        ];
    }
}
