<?php

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\ApiFormat;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ApiFormatSettingsListLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'api_formats';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('display_name', 'Name')
                ->sort()
                ->render(function (ApiFormat $apiFormat) {
                    return Link::make($apiFormat->display_name)
                        ->route('platform.models.api.formats.edit', $apiFormat->id);
                }),

            TD::make('unique_name', 'Unique Name')
                ->sort()
                ->render(function (ApiFormat $apiFormat) {
                    return '<code class="text-muted">'.e($apiFormat->unique_name).'</code>';
                }),

            TD::make('provider_class', 'Provider Class')
                ->sort()
                ->render(function (ApiFormat $apiFormat) {
                    if (!$apiFormat->provider_class) {
                        return '<span class="text-muted">-</span>';
                    }
                    
                    $className = class_basename($apiFormat->provider_class);
                    return '<code class="text-muted small">'.e($className).'</code>';
                }),

            TD::make('endpoints_count', 'Endpoints')
                ->sort()
                ->render(function (ApiFormat $apiFormat) {
                    $count = $apiFormat->endpoints_count ?? $apiFormat->endpoints->count();

                    return '<span class="badge bg-info">'.$count.' Endpoint'.($count != 1 ? 's' : '').'</span>';
                }),

            TD::make('api_providers_count', 'Usage')
                ->sort()
                ->render(function (ApiFormat $apiFormat) {
                    $count = $apiFormat->api_providers_count ?? $apiFormat->providers()->count();
                    if ($count > 0) {
                        return '<span class="badge bg-success">'.$count.' Provider'.($count != 1 ? 's' : '').'</span>';
                    }

                    return '<span class="badge bg-secondary">Not used</span>';
                }),

            TD::make('metadata', 'Features')
                ->render(function (ApiFormat $apiFormat) {
                    $features = [];
                    if ($apiFormat->metadata) {
                        $metadata = is_array($apiFormat->metadata) ? $apiFormat->metadata : json_decode($apiFormat->metadata, true);

                        if (isset($metadata['supports_streaming']) && $metadata['supports_streaming']) {
                            $features[] = '<span class="badge bg-primary">Streaming</span>';
                        }
                        if (isset($metadata['supports_function_calling']) && $metadata['supports_function_calling']) {
                            $features[] = '<span class="badge bg-warning">Functions</span>';
                        }
                        if (isset($metadata['supports_grounding']) && $metadata['supports_grounding']) {
                            $features[] = '<span class="badge bg-info">Grounding</span>';
                        }
                    }

                    return implode(' ', $features);
                }),

            TD::make('created_at', 'Created')
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->defaultHidden()
                ->sort(),

            TD::make('updated_at', 'Last Updated')
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            TD::make('actions', 'Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('120px')
                ->render(function (ApiFormat $apiFormat) {
                    return DropDown::make()
                        ->icon('bs.three-dots-vertical')
                        ->list([
                            Link::make('Edit')
                                ->icon('bs.pencil')
                                ->route('platform.models.api.formats.edit', $apiFormat->id),

                            Button::make('Delete')
                                ->icon('bs.trash')
                                ->confirm('Are you sure you want to delete this API format?')
                                ->method('deleteApiFormat')
                                ->parameters(['id' => $apiFormat->id])
                                ->canSee($apiFormat->providers()->count() === 0), // Only show if not used by providers
                        ]);
                }),
        ];
    }
}
