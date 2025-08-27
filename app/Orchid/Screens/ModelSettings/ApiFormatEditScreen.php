<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ApiFormat;
use App\Models\ApiFormatEndpoint;
use App\Orchid\Layouts\ModelSettings\ApiFormatSettingsEditLayout;
use App\Orchid\Layouts\ModelSettings\ApiFormatEndpointsLayout;
use App\Orchid\Traits\OrchidLoggingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ApiFormatEditScreen extends Screen
{
    use OrchidLoggingTrait;

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
            $endpointsData = $apiFormat->endpoints->map(function($endpoint) {
                return [
                    'Name' => $endpoint->name,
                    'Path' => $endpoint->path,
                    'Method' => $endpoint->method,
                    'Is Active' => $endpoint->is_active ? '1' : '0'
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
                    'Is Active' => '1'
                ]
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
            return 'Edit API Format: ' . $this->apiFormat->display_name;
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
            'systems.modelsettings',
        ];
    }

    /**
     * Save API format.
     */
    public function save(Request $request, ApiFormat $apiFormat)
    {
        try {
            $data = $request->validate([
                'apiFormat.unique_name' => 'required|string|max:255|unique:api_formats,unique_name,' . $apiFormat->id,
                'apiFormat.display_name' => 'required|string|max:255',
                'apiFormat.base_url' => 'required|url|max:500',
                'apiFormat.metadata' => 'nullable|json',
                'endpoints' => 'required|array|min:1',
                'endpoints.*.Name' => 'required|string|max:255',
                'endpoints.*.Path' => 'required|string|max:500',
                'endpoints.*.Method' => 'required|string|in:GET,POST,PUT,DELETE,PATCH',
            ]);

            // Store original values for change tracking
            $originalUniqueName = $apiFormat->unique_name;
            $originalDisplayName = $apiFormat->display_name;
            $originalBaseUrl = $apiFormat->base_url;
            $originalMetadata = $apiFormat->metadata;
            $originalEndpointsCount = $apiFormat->endpoints()->count();

            // Save API format
            $apiFormat->fill($data['apiFormat']);
            
            // Parse metadata JSON
            if (!empty($data['apiFormat']['metadata'])) {
                $decoded = json_decode($data['apiFormat']['metadata'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('Invalid JSON in API format metadata', [
                        'api_format_id' => $apiFormat->id,
                        'json_error' => json_last_error_msg(),
                        'input' => $data['apiFormat']['metadata'],
                    ]);
                    
                    Toast::error('Metadata must be valid JSON format.');
                    return back()->withInput();
                }
                $apiFormat->metadata = $decoded;
            } else {
                $apiFormat->metadata = null;
            }
            
            $apiFormat->save();

            // Handle endpoints from Matrix field
            $endpointsData = $data['endpoints'];
            
            // Filter out empty endpoints
            $endpointsData = array_filter($endpointsData, function($endpoint) {
                return !empty($endpoint['Name']) && !empty($endpoint['Path']) && !empty($endpoint['Method']);
            });

            // Delete existing endpoints
            $apiFormat->endpoints()->delete();

            // Create new endpoints
            foreach ($endpointsData as $endpointData) {
                $apiFormat->endpoints()->create([
                    'name' => $endpointData['Name'],
                    'path' => $endpointData['Path'],
                    'method' => strtoupper($endpointData['Method']),
                    'is_active' => !empty($endpointData['Is Active']) && $endpointData['Is Active'] !== '0',
                ]);
            }

            // Log successful update with change details
            $changes = [];
            if ($originalUniqueName !== $apiFormat->unique_name) {
                $changes['unique_name'] = ['from' => $originalUniqueName, 'to' => $apiFormat->unique_name];
            }
            if ($originalDisplayName !== $apiFormat->display_name) {
                $changes['display_name'] = ['from' => $originalDisplayName, 'to' => $apiFormat->display_name];
            }
            if ($originalBaseUrl !== $apiFormat->base_url) {
                $changes['base_url'] = ['from' => $originalBaseUrl, 'to' => $apiFormat->base_url];
            }
            if ($originalMetadata !== $apiFormat->metadata) {
                $changes['metadata'] = 'updated';
            }
            if ($originalEndpointsCount !== count($endpointsData)) {
                $changes['endpoints_count'] = ['from' => $originalEndpointsCount, 'to' => count($endpointsData)];
            }

            Log::info('API format updated successfully', [
                'api_format_id' => $apiFormat->id,
                'api_format_name' => $apiFormat->display_name,
                'endpoints_count' => count($endpointsData),
                'changes' => $changes,
                'updated_by' => auth()->id(),
            ]);

            Toast::success("API format '{$apiFormat->display_name}' has been updated successfully.");

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
            
            Toast::error('Error saving API format: ' . $e->getMessage());
            return back()->withInput();
        }
    }
}
