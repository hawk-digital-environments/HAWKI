<?php

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ProviderSetting;
use App\Services\ProviderSettingsService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Switcher;

use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ProviderSettingsScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Load all existing providers from the database
        $providers = ProviderSetting::all()->keyBy('id');
        
        return [
            'providers' => $providers,
            'hasProviders' => $providers->isNotEmpty(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'API Provider Settings';
    }
    /**
     * Display header description.
     */
     public function description(): ?string
    {
        return 'Configure the API Provider connections.';
    }
    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Import from Config')
                ->icon('cloud-download')
                ->method('importFromConfig')
                ->confirm('Are you sure? Existing providers will not be overwritten.'),
                
            Link::make(__('Add'))
                ->icon('bs.plus-circle')
                ->route('platform.modelsettings.provider.create'),

            Button::make('Save')
                ->icon('save')
                ->method('saveSettings'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        $providers = $this->query()['providers'];
        
        // If no providers exist, show the info template
        if ($providers->isEmpty()) {
            return [
                Layout::view('orchid.provider-settings.no-providers')
            ];
        }
        
        // Otherwise, show the tabs with the providers
        $tabs = [];
        
        foreach ($providers as $provider) {
            $tabTitle = ucfirst($provider->provider_name);
            $tabs[$tabTitle] = $this->providerLayout($provider);
        }
        
        return [
            Layout::accordion([
            'Show more Information' => [
                Layout::view('orchid.provider-settings.provider-info')
            ]
            ])->open([]),

            Layout::tabs($tabs)
        ];
    }
    
    /**
     * Generate layout for each provider.
     */
    private function providerLayout($provider): iterable
    {
        $providerId = $provider->id;
        $providerName = $provider->provider_name;
        // Basic fields for all providers
        $fields = [
            //Group::make([
            Input::make("providers.{$providerId}.provider_name")
                ->title('Provider Name')
                ->value($providerName),

            Switcher::make("providers.{$providerId}.is_active")
                                        ->sendTrueOrFalse()
                                        ->value($provider->is_active)
                                        ->title('Active')
                                        ->help('Activate this provider for use in the application'),
            //]),
            Select::make("providers.{$providerId}.api_format")
                ->title('API Interface')
                ->options($this->getProviderSchemas())
                ->help('The API interface to use for this provider'),
                
            Input::make("providers.{$providerId}.api_key")
                ->title('API Key')
                ->type('password')
                ->help('The API key for authentication'),
                
            Input::make("providers.{$providerId}.base_url")
                ->title('API URL')
                ->placeholder('https://api.example.com/v1')
                ->help('The URL for API requests'),
                
            Input::make("providers.{$providerId}.ping_url")
                ->title('Models URL')
                ->placeholder('https://api.example.com/v1/models')
                ->help('The URL to retrieve the available models'),
                
            Button::make('Delete')
                ->icon('trash')
                ->confirm('Are you sure you want to delete this provider? This action cannot be undone.')
                ->method('deleteProvider', ['id' => $providerId]),
        ];
        
        return [
            Layout::rows($fields)
        ];
    }
    
    /**
     * Get available provider schemas from config.
     *
     * @return array
     */
    private function getProviderSchemas(): array
    {
        $config = config('model_providers', []);
        
        // If the file contains a providers key, we take its child elements
        $providers = $config['providers'] ?? [];
        
        $options = [];
        foreach ($providers as $key => $provider) {
            // Use the provider's name or the key as the display name
            $options[$key] = $provider['name'] ?? ucfirst($key);
        }
        
        return $options;
    }
    
    /**
     * Save settings to the database.
     */
    public function saveSettings(Request $request, ProviderSettingsService $settingsService)
    {
        $providers = $request->get('providers');
        
        foreach ($providers as $providerId => $settings) {
            $provider = ProviderSetting::find($providerId);
            if ($provider) {
                $provider->update($settings);
            }
        }
        
        Toast::info('Provider settings saved successfully.');
        
        return redirect()->back();
    }

    /**
     * Import settings from config file.
     */
    public function importFromConfig(ProviderSettingsService $settingsService)
    {
        $stats = $settingsService->importFromConfig();
        
        if ($stats['imported'] > 0) {
            Toast::success("{$stats['imported']} providers were imported from the configuration file.");
        } else {
            Toast::info("No new providers imported. {$stats['skipped']} providers already exist.");
        }
        
        return redirect()->back();
    }

    /**
     * Delete a provider.
     * 
     * @param Request $request
     * @param ProviderSettingsService $settingsService
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteProvider(Request $request, ProviderSettingsService $settingsService)
    {
        $providerId = $request->get('id');
        $provider = ProviderSetting::find($providerId);
        
        if (!$provider) {
            Toast::error('Provider not found.');
            return redirect()->back();
        }
        
        $providerName = $provider->provider_name;
        
        // Delete provider
        $provider->delete();
        
        Toast::success("Provider '{$providerName}' was successfully deleted.");
        return redirect()->back();
    }
}
