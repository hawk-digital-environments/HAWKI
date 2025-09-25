<?php

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ApiFormat;
use App\Orchid\Layouts\ModelSettings\ApiFormatFiltersLayout;
use App\Orchid\Layouts\ModelSettings\ApiFormatSettingsListLayout;
use App\Orchid\Layouts\ModelSettings\ApiManagementTabMenu;
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
            'api_formats' => ApiFormat::with(['endpoints', 'providers'])
                ->withCount(['endpoints', 'providers'])
                ->filters(ApiFormatFiltersLayout::class)
                ->defaultSort('display_name')
                ->paginate(15),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'API Format Management';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage API format settings and structure definitions for different AI providers.';
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
                ->route('platform.models.api.formats.create'),
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
            ApiManagementTabMenu::class,
            ApiFormatFiltersLayout::class,
            ApiFormatSettingsListLayout::class,
        ];
    }

    /**
     * Permission for this screen
     */
    public function permission(): ?iterable
    {
        return ['platform.modelsettings.providers'];
    }

    /**
     * Delete API format
     */
    public function deleteApiFormat(Request $request)
    {
        try {
            $apiFormat = ApiFormat::findOrFail($request->get('id'));

            // Check if API format is being used by any providers
            $usedByProviders = $apiFormat->providers()->count();

            if ($usedByProviders > 0) {
                Toast::error("API format '{$apiFormat->display_name}' cannot be deleted because it is used by {$usedByProviders} provider(s).");

                return redirect()->route('platform.models.api.formats');
            }

            // Delete endpoints first
            $apiFormat->endpoints()->delete();

            // Delete the API format
            $apiFormat->delete();

            $this->logSuccess("API format '{$apiFormat->display_name}' has been deleted successfully");
            Toast::success("API format '{$apiFormat->display_name}' has been deleted successfully.");

        } catch (\Exception $e) {
            $this->logError('Error deleting API format', $e);
            Toast::error('Error deleting API format: '.$e->getMessage());
        }

        return redirect()->route('platform.models.api.formats');
    }
}
