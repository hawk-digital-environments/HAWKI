<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ProviderSetting;
use App\Orchid\Layouts\ModelSettings\ProviderSettingsEditLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class ProviderCreateScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'provider' => new ProviderSetting(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Create New Provider';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Add a new API provider to the system.';
    }

    /**
     * Permission required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.modelsettings.providers',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Create Provider')
                ->icon('save')
                ->method('create'),

            Link::make('Cancel')
                ->icon('x-circle')
                ->route('platform.modelsettings.providers'),
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
            ProviderSettingsEditLayout::class,
        ];
    }

    /**
     * Create a new provider.
     */
    public function create(Request $request)
    {
        $request->validate([
            'provider.provider_name' => 'required|string|max:255|unique:provider_settings,provider_name',
            'provider.api_format' => 'required|string|max:255',
            'provider.base_url' => 'nullable|url|max:500',
            'provider.ping_url' => 'nullable|url|max:500',
            'provider.api_key' => 'nullable|string|max:500',
            'provider.is_active' => 'boolean',
            'provider.additional_settings' => 'nullable|string',
        ]);
        
        $providerData = $request->input('provider');
        $providerName = $providerData['provider_name'];
        
        // Convert JSON string to array for storage
        if (!empty($providerData['additional_settings'])) {
            $decoded = json_decode($providerData['additional_settings'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Toast::error('Additional settings must be valid JSON format.');
                return back()->withInput();
            }
            $providerData['additional_settings'] = $decoded;
        } else {
            $providerData['additional_settings'] = null;
        }
        
        // Create the new provider
        ProviderSetting::create($providerData);
        
        Toast::success("New provider '{$providerName}' was successfully created.");
        return redirect()->route('platform.modelsettings.providers');
    }
}
