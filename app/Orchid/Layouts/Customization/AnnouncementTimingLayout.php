<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class AnnouncementTimingLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): iterable
    {
        // Get available anchors from config
        $anchors = collect(config('announcements.anchors', []))
            ->mapWithKeys(fn($anchor, $key) => [$key => $anchor['name']]);
        
        // Add empty option for no anchor
        $anchors->prepend('No anchor (show immediately)', '');

        return [
            Select::make('announcement.anchor')
                ->title('Anchor')
                ->options($anchors->toArray())
                ->empty('No anchor (show immediately)', '')
                ->help('Optional: Trigger announcement on specific frontend event'),

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
        ];
    }
}
