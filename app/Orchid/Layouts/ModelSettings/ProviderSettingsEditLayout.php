<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\ProviderSetting;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class ProviderSettingsEditLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('provider.provider_name')
                ->title('Provider Name')
                ->required()
                ->help('Unique name for this provider'),

            Select::make('provider.api_format')
                ->title('API Format')
                ->options($this->getApiFormatOptions())
                ->required()
                ->help('The API interface format to use'),

            Input::make('provider.base_url')
                ->title('Base URL')
                ->type('url')
                ->placeholder('https://api.example.com/v1')
                ->help('The base URL for API requests'),

            Input::make('provider.ping_url')
                ->title('Models URL')
                ->type('url')
                ->placeholder('https://api.example.com/v1/models')
                ->help('The URL to retrieve available models'),

            Input::make('provider.api_key')
                ->title('API Key')
                ->type('password')
                ->help('Authentication key for the API'),

            Switcher::make('provider.is_active')
                ->title('Active')
                ->sendTrueOrFalse()
                ->help('Enable this provider for use in the application'),

            TextArea::make('provider.additional_settings')
                ->title('Additional Settings (JSON)')
                ->rows(5)
                ->help('Additional configuration in JSON format (optional)'),
        ];
    }

    /**
     * Get available API format options
     *
     * @return array
     */
    private function getApiFormatOptions(): array
    {
        // Get existing formats from database
        $formats = ProviderSetting::whereNotNull('api_format')
            ->distinct()
            ->pluck('api_format')
            ->toArray();
        
        $options = [];
        foreach ($formats as $format) {
            $options[$format] = ucfirst($format);
        }
        
        // Add common formats if they don't exist
        $commonFormats = [
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'ollama' => 'Ollama',
            'google' => 'Google AI',
            'mistral' => 'Mistral AI',
        ];
        
        foreach ($commonFormats as $key => $label) {
            if (!isset($options[$key])) {
                $options[$key] = $label;
            }
        }
        
        return $options;
    }
}
