<?php

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class ApiFormatSettingsEditLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    protected function fields(): iterable
    {
        return [
            Input::make('apiFormat.unique_name')
                ->title('Unique Name')
                ->placeholder('e.g. openai-api')
                ->help('Internal identifier for the API format (no spaces)')
                ->required(),

            Input::make('apiFormat.display_name')
                ->title('Display Name')
                ->placeholder('e.g. OpenAI API')
                ->help('User-friendly name for display')
                ->required(),



            Select::make('apiFormat.provider_class')
                ->title('Provider Class')
                ->help('PHP class that handles this API format')
                ->options([
                    'App\\Services\\AI\\Providers\\OpenAIProvider' => 'OpenAI Provider',
                    'App\\Services\\AI\\Providers\\GoogleProvider' => 'Google Provider',
                    'App\\Services\\AI\\Providers\\AnthropicProvider' => 'Anthropic Provider',
                    'App\\Services\\AI\\Providers\\OllamaProvider' => 'Ollama Provider',
                    'App\\Services\\AI\\Providers\\GWDGProvider' => 'GWDG Provider',
                    'App\\Services\\AI\\Providers\\OpenWebUIProvider' => 'OpenWebUI Provider',
                ])
                ->empty('Select Provider Class', '')
                ->required(),

            TextArea::make('apiFormat.metadata')
                ->title('Metadata (JSON)')
                ->rows(8)
                ->help('Additional configuration and metadata in JSON format')
                ->value(function ($repository) {
                    // In Orchid, data comes from Repository, we need to extract the actual model
                    if (is_object($repository) && method_exists($repository, 'get')) {
                        $apiFormat = $repository->get('apiFormat');
                    } else {
                        $apiFormat = $repository;
                    }

                    // Handle array or object
                    if (is_array($apiFormat)) {
                        $apiFormat = (object) $apiFormat;
                    }

                    if ($apiFormat && isset($apiFormat->metadata)) {
                        $metadata = is_string($apiFormat->metadata) ? json_decode($apiFormat->metadata, true) : $apiFormat->metadata;
                        if ($metadata) {
                            return json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        }
                    }

                    return json_encode([
                        'auth_type' => 'bearer',
                        'content_type' => 'application/json',
                        'supports_streaming' => true,
                        'supports_function_calling' => false,
                        'compatible_providers' => [],
                    ], JSON_PRETTY_PRINT);
                }),
        ];
    }
}
