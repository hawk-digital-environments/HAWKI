<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ApiFormat;
use App\Models\ApiFormatEndpoint;
use App\Orchid\Layouts\ModelSettings\ApiFormatSettingsEditLayout;
use App\Orchid\Layouts\ModelSettings\ApiFormatEndpointsLayout;
use App\Orchid\Traits\OrchidLoggingTrait;
use Illuminate\Http\Request;
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
        return [
            'apiFormat' => $apiFormat->load('endpoints'),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->apiFormat->exists ? 'Edit API Format' : 'Create API Format';
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
                ->icon('bs.check-circle')
                ->method('save'),

            Link::make('Cancel')
                ->icon('bs.x-circle')
                ->route('platform.modelsettings.api-format'),
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
                'endpoints_json' => 'required|json',
            ]);

            // Save API format
            $apiFormat->fill($data['apiFormat']);
            
            // Parse metadata JSON
            if (!empty($data['apiFormat']['metadata'])) {
                $decoded = json_decode($data['apiFormat']['metadata'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Toast::error('Metadata must be valid JSON format.');
                    return back()->withInput();
                }
                $apiFormat->metadata = $decoded;
            } else {
                $apiFormat->metadata = null;
            }
            
            $apiFormat->save();

            // Handle endpoints JSON
            $endpointsData = json_decode($data['endpoints_json'], true);
            
            // Validate endpoints structure
            if (!is_array($endpointsData)) {
                throw new \Exception('Endpoints must be provided as JSON array.');
            }
            
            // Filter out empty endpoints
            $endpointsData = array_filter($endpointsData, function($endpoint) {
                return !empty($endpoint['name']) && !empty($endpoint['path']) && !empty($endpoint['method']);
            });

            // Delete existing endpoints
            $apiFormat->endpoints()->delete();

            // Create new endpoints
            foreach ($endpointsData as $endpointData) {
                if (!isset($endpointData['name']) || !isset($endpointData['path']) || !isset($endpointData['method'])) {
                    throw new \Exception('Each endpoint must contain "name", "path" and "method" fields.');
                }

                if (!in_array(strtoupper($endpointData['method']), ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])) {
                    throw new \Exception('Invalid HTTP method: ' . $endpointData['method']);
                }

                $apiFormat->endpoints()->create([
                    'name' => $endpointData['name'],
                    'path' => $endpointData['path'],
                    'method' => strtoupper($endpointData['method']),
                ]);
            }

            $this->logSuccess("API format '{$apiFormat->display_name}' has been saved successfully");
            Toast::success('API format has been saved successfully.');

            return redirect()->route('platform.modelsettings.api-format');

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->logError('Validation error while saving API format', $e);
            Toast::error('Please check your input data.');
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error while saving API format', $e);
            Toast::error('Error saving API format: ' . $e->getMessage());
            
            return redirect()->back()->withInput();
        }
    }
}
