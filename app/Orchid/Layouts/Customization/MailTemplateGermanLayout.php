<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Rows;

class MailTemplateGermanLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): iterable
    {
        return [
            Input::make('mailTemplate.de_subject')
                ->title('German Subject (de)')
                ->placeholder('Enter German email subject')
                ->help('German email subject line'),

            Code::make('mailTemplate.de_body')
                ->title('German Body (de)')
                ->placeholder('Enter German email content')
                ->language('html')
                ->lineNumbers()
                ->rows(10)
                ->help('German email content (HTML)'),
        ];
    }
}
