<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\SimpleMDE;
use Orchid\Screen\Layouts\Rows;

class AnnouncementContentLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): iterable
    {
        return [
            SimpleMDE::make('de_content')
                ->title('German Content (de_DE)')
                ->help('Markdown content for German language')
                ->placeholder('# Überschrift

Schreiben Sie hier Ihren Inhalt in Markdown-Format...

## Beispiel

- Liste 1
- Liste 2

**Fettgedruckt** und *kursiv*'),

            SimpleMDE::make('en_content')
                ->title('English Content (en_US)')
                ->help('Markdown content for English language')
                ->placeholder('# Heading

Write your content here in Markdown format...

## Example

- List item 1
- List item 2

**Bold** and *italic*'),
        ];
    }
}
