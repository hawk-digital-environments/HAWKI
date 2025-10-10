<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class UserImportLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('json_file')
                ->type('file')
                ->accept('.json')
                ->required()
                ->title('JSON File')
                ->help('Select a JSON file containing user data to import. Maximum file size: 2MB')
                ->placeholder('Choose JSON file...'),

            TextArea::make('format_example')
                ->title('Expected JSON Format')
                ->readonly()
                ->rows(10)
                ->value('
[
  {
    "username": "testuser",
    "password": "123",
    "name": "Test User",
    "email": "testuser@example.com",
    "employeetype": "student",
    "avatar_id": "avatar.jpg"
  }
]')
                ->help('Required fields: username, name, email. Optional fields: password, employeetype, avatar_id.'),
        ];
    }
}
