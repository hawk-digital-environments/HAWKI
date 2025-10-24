<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Rows;

class MailTemplateEnglishLayout extends Rows
{
    /**
     * Layout title
     *
     * @var string
     */
    protected $title = 'English Content';

    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    public function fields(): iterable
    {
        return [
            Input::make('mailTemplate.en_subject')
                ->title('English Subject (en)')
                ->placeholder('Enter English email subject')
                ->help('English email subject line'),

            Code::make('mailTemplate.en_body')
                ->title('English Body (en)')
                ->placeholder('Enter English email content')
                ->language('html')
                ->lineNumbers()
                ->rows(15)
                ->help('English email content (HTML)')
                ->style('resize: vertical; min-height: 500px;'),
        ];
    }
}
