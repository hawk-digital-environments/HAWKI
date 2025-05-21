<?php

namespace App\Orchid\Screens\ModelSettings;

use App\Models\ProviderSetting;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
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
        return [];
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
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Create')
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
            Layout::rows([
                Input::make('provider_name')
                    ->title('Provider Name')
                    ->placeholder('e.g. openai')
                    ->required()
                    ->help('The name of the provider (alphanumeric characters only)'),
                
                Select::make('api_interface')
                    ->title('API Interface')
                    ->options($this->getProviderInterface())
                    ->autofocus(false)
                    ->help('The API interface to use for this provider'),
                
                CheckBox::make('is_active')
                    ->title('Active')
                    ->value(false)
                    ->help('Activate this provider for use in the application'),
                
                Input::make('api_key')
                    ->title('API Key')
                    ->type('password')
                    ->help('The API key for authentication'),
                
                Input::make('base_url')
                    ->title('API URL')
                    ->placeholder('https://api.example.com/v1/chat/completions')
                    ->help('The URL for API requests'),
                
                Input::make('ping_url')
                    ->title('Models URL')
                    ->placeholder('https://api.example.com/v1/models')
                    ->help('The URL to retrieve the available models'),
            ])
        ];
    }

    /**
     * Get available provider schemas from config.
     *
     * @return array
     */
    private function getProviderInterface(): array
    {
        $config = config('model_providers', []);
        
        // Wenn die Datei einen providers-Schlüssel enthält, nehmen wir die Kindelemente davon
        $providers = $config['providers'] ?? [];
        
        $options = [];
        foreach ($providers as $key => $provider) {
            // Verwende den Namen des Providers oder den Schlüssel als Anzeigename
            $options[$key] = $provider['name'] ?? ucfirst($key);
        }
        
        return $options;
    }

    /**
     * Create a new provider.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create(Request $request)
    {
        $request->validate([
            'provider_name' => 'required|string',
        ]);
        
        $providerName = $request->input('provider_name');
        
        if (empty($providerName)) {
            Toast::warning('Der Provider-Name muss alphanumerische Zeichen enthalten.');
            return back()->withInput();
        }
        
        // Überprüfe, ob der Provider bereits existiert
        if (ProviderSetting::where('provider_name', $providerName)->exists()) {
            Toast::error("Ein Provider mit dem Namen '{$providerName}' existiert bereits.");
            return back()->withInput();
        }
        
        // Erstelle einen neuen Provider
        ProviderSetting::create([
            'provider_name' => $providerName,
            'api_format' => $request->input('api_schema'),
            'is_active' => $request->boolean('is_active'),
            'api_key' => $request->input('api_key'),
            'base_url' => $request->input('base_url'),
            'ping_url' => $request->input('ping_url'),
        ]);
        
        Toast::success("Neuer Provider '{$providerName}' wurde erfolgreich erstellt.");
        return redirect()->route('platform.modelsettings.providers');
    }
}
