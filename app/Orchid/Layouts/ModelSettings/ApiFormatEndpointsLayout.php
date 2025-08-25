<?php

namespace App\Orchid\Layouts\ModelSettings;

use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class ApiFormatEndpointsLayout extends Rows
{
    /**
     * Get the fields elements to be displayed.
     *
     * @return Field[]
     */
    protected function fields(): iterable
    {
        return [
            TextArea::make('endpoints_json')
                ->title('API Endpoints (JSON)')
                ->help('Define the endpoints as JSON array. Format: [{"name":"chat.create","path":"/chat/completions","method":"POST"}]')
                ->rows(10)
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
                    
                    // If it's a model instance, use the endpoints relationship
                    if ($apiFormat && is_object($apiFormat) && method_exists($apiFormat, 'endpoints')) {
                        $endpoints = $apiFormat->endpoints;
                        if ($endpoints && method_exists($endpoints, 'count') && $endpoints->count() > 0) {
                            $endpointsArray = $endpoints->map(function($endpoint) {
                                return [
                                    'name' => $endpoint->name,
                                    'path' => $endpoint->path,
                                    'method' => $endpoint->method
                                ];
                            })->toArray();
                            return json_encode($endpointsArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        }
                    }
                    
                    // Default empty structure
                    return json_encode([
                        [
                            'name' => 'chat.create',
                            'path' => '/chat/completions',
                            'method' => 'POST'
                        ]
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                })
                ->placeholder('JSON array of endpoints'),
        ];
    }
}
