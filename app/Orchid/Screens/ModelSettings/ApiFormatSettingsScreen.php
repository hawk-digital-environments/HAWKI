<?php

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ApiFormat;
use App\Orchid\Layouts\ModelSettings\ApiFormatSettingsListLayout;
use App\Orchid\Traits\OrchidLoggingTrait;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ApiFormatSettingsScreen extends Screen
{
    use OrchidLoggingTrait;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'api_formats' => ApiFormat::with('endpoints')
                ->orderBy('display_name')
                ->paginate(15)
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'API Format Management';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Add')
                ->icon('bs.plus-circle')
                ->route('platform.modelsettings.api-format.create'),
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
            Layout::view('platform::partials.alert'),
            
            ApiFormatSettingsListLayout::class,
        ];
    }

    /**
     * Permission for this screen
     */
    public function permission(): ?iterable
    {
        return ['systems.modelsettings'];
    }

    /**
     * Delete API format
     */
    public function deleteApiFormat(Request $request)
    {
        try {
            $apiFormat = ApiFormat::findOrFail($request->get('id'));
            
            // Check if API format is being used by any providers
            $usedByProviders = $apiFormat->providerSettings()->count();
            
            if ($usedByProviders > 0) {
                Toast::error("API format '{$apiFormat->display_name}' cannot be deleted because it is used by {$usedByProviders} provider(s).");
                return redirect()->route('platform.modelsettings.api-format');
            }
            
            // Delete endpoints first
            $apiFormat->endpoints()->delete();
            
            // Delete the API format
            $apiFormat->delete();
            
            $this->logSuccess("API format '{$apiFormat->display_name}' has been deleted successfully");
            Toast::success("API format '{$apiFormat->display_name}' has been deleted successfully.");
            
        } catch (\Exception $e) {
            $this->logError('Error deleting API format', $e);
            Toast::error('Error deleting API format: ' . $e->getMessage());
        }
        
        return redirect()->route('platform.modelsettings.api-format');
    }
}
