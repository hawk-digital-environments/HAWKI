<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ApiFormat;
use App\Orchid\Layouts\ModelSettings\ApiFormatEndpointsLayout;
use App\Orchid\Layouts\ModelSettings\ApiFormatSettingsEditLayout;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ApiFormatEditScreen extends Screen
{
    use OrchidLoggingTrait, OrchidSettingsManagementTrait {
        OrchidLoggingTrait::logBatchOperation insteadof OrchidSettingsManagementTrait;
    }

    /**
     * @var ApiFormat
     */
    public $apiFormat;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(ApiFormat $apiFormat): iterable
    {
        // Prepare endpoints data for Matrix field
        $endpointsData = [];
        if ($apiFormat->exists && $apiFormat->endpoints) {
            $endpointsData = $apiFormat->endpoints->map(function ($endpoint) {
                return [
                    'Name' => $endpoint->name,
                    'Path' => $endpoint->path,
                    'Method' => $endpoint->method,
                    'Is Active' => $endpoint->is_active ? '1' : '0',
                ];
            })->toArray();
        }

        // If no endpoints exist, provide one empty row
        if (empty($endpointsData)) {
            $endpointsData = [
                [
                    'Name' => '',
                    'Path' => '',
                    'Method' => 'POST',
                    'Is Active' => '1',
                ],
            ];
        }

        return [
            'apiFormat' => $apiFormat,
            'endpoints' => $endpointsData,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        if ($this->apiFormat->exists) {
            return 'Edit API Format: '.$this->apiFormat->display_name;
        }

        return 'Create API Format';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return $this->apiFormat->exists
            ? 'Modify the configuration for this API format.'
            : 'Create a new API format configuration.';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Save')
                ->icon('save')
                ->method('save'),

            Link::make('Cancel')
                ->icon('x-circle')
                ->route('platform.models.api.formats'),

        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::block(ApiFormatSettingsEditLayout::class)
                ->title('Basic Information')
                ->description('Configure the basic settings for this API format.'),

            Layout::block(ApiFormatEndpointsLayout::class)
                ->title('API Endpoints')
                ->description('Define the available endpoints for this API format.'),
        ];
    }

    /**
     * Permission required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.modelsettings.settings',
        ];
    }

    /**
     * Save API format.
     */
    public function save(Request $request, ApiFormat $apiFormat)
    {
        try {
            $data = $request->validate([
                'apiFormat.unique_name' => 'required|string|max:255|unique:api_formats,unique_name,'.$apiFormat->id,
                'apiFormat.display_name' => 'required|string|max:255',
                'apiFormat.metadata' => 'nullable|json',
                'apiFormat.provider_class' => 'nullable|string|max:255',
                'endpoints' => 'required|array|min:1',
                'endpoints.*.Name' => 'required|string|max:255',
                'endpoints.*.Path' => 'required|string|max:500',
                'endpoints.*.Method' => 'required|string|in:GET,POST,PUT,DELETE,PATCH',
                'endpoints.*.Is Active' => 'nullable|string|in:0,1',
            ]);

            // Store original values for change tracking
            $originalValues = $apiFormat->getOriginal();
            $originalEndpointsCount = $apiFormat->endpoints()->count();

            $apiFormatData = $data['apiFormat'];

            // Validate and process JSON metadata
            if (! empty($apiFormatData['metadata'])) {
                $decoded = $this->validateAndProcessJsonField(
                    $apiFormatData['metadata'],
                    'Metadata',
                    $apiFormat->id
                );

                if ($decoded === false) {
                    return back()->withInput();
                }

                $apiFormatData['metadata'] = $decoded;
            } else {
                $apiFormatData['metadata'] = null;
            }

            // Handle endpoints processing
            $endpointsData = $data['endpoints'];

            // Filter out empty endpoints
            $endpointsData = array_filter($endpointsData, function ($endpoint) {
                return ! empty($endpoint['Name']) && ! empty($endpoint['Path']) && ! empty($endpoint['Method']);
            });

            // Use trait method for save with change detection and custom after-save logic
            $result = $this->saveModelWithChangeDetection(
                $apiFormat,
                $apiFormatData,
                $apiFormat->display_name,
                $originalValues,
                null, // no before-save callback
                function ($model) use ($endpointsData, $originalEndpointsCount) {
                    // Delete existing endpoints
                    $model->endpoints()->delete();

                    // Create new endpoints
                    foreach ($endpointsData as $endpointData) {
                        // Convert 'Is Active' value to boolean
                        $isActive = isset($endpointData['Is Active']) && $endpointData['Is Active'] === '1';

                        $model->endpoints()->create([
                            'name' => $endpointData['Name'],
                            'path' => $endpointData['Path'],
                            'method' => strtoupper($endpointData['Method']),
                            'is_active' => $isActive,
                        ]);
                    }

                    // Add endpoint change info to log
                    if ($originalEndpointsCount !== count($endpointsData)) {
                        Log::info("API format endpoints updated - {$model->display_name}", [
                            'api_format_id' => $model->id,
                            'endpoints_count_change' => ['from' => $originalEndpointsCount, 'to' => count($endpointsData)],
                            'endpoints_details' => collect($endpointsData)->map(function ($endpoint) {
                                return [
                                    'name' => $endpoint['Name'],
                                    'method' => $endpoint['Method'],
                                    'is_active' => isset($endpoint['Is Active']) && $endpoint['Is Active'] === '1',
                                ];
                            })->toArray(),
                            'updated_by' => auth()->id(),
                        ]);
                    }
                }
            );

            // Redirect back to the edit screen instead of the list
            return redirect()->route('platform.models.api.formats.edit', $apiFormat);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed for API format update', [
                'api_format_id' => $apiFormat->id,
                'errors' => $e->errors(),
                'updated_by' => auth()->id(),
            ]);

            throw $e; // Re-throw validation exceptions to show form errors
        } catch (\Exception $e) {
            Log::error('Error updating API format', [
                'api_format_id' => $apiFormat->id,
                'api_format_name' => $apiFormat->display_name ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'updated_by' => auth()->id(),
            ]);

            Toast::error('Error saving API format: '.$e->getMessage());

            return back()->withInput();
        }
    }
}
