<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class ApiProvidersImportLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('importFile')
                ->type('file')
                ->accept('.json,.php')
                ->required()
                ->title('Provider File')
                ->help('Select a JSON or PHP configuration file to import providers. Maximum file size: 2MB')
                ->placeholder('Choose file...'),

            TextArea::make('format_example')
                ->title('Supported File Formats')
                ->readonly()
                ->rows(15)
                ->value('JSON Format:
[
  {
    "provider_name": "ollama",
    "api_key": "",
    "base_url": "http://localhost:11434/api/chat",
    "additional_settings": "{\\"ping_url\\": \\"http://localhost:11434/api/tags\\"}",
    "is_active": true
  }
]

PHP Config Format (model_providers.php):
<?php
return [
  "providers" => [
    "ollama" => [
      "id" => "ollama",
      "api_key" => "",
      "api_url" => "http://localhost:11434/api/chat",
      "ping_url" => "http://localhost:11434/api/tags"
    ]
  ]
];')
                ->help('Both JSON and PHP config files are automatically detected and processed. PHP files will be converted to the required database format.'),
        ];
    }
}
