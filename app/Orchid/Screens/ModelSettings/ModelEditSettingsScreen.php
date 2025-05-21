<?php

namespace App\Orchid\Screens\ModelSettings;

use App\Models\LanguageModel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;


class ModelEditSettingsScreen extends Screen
{
    /**
     * @var LanguageModel
     */
    public $model;

    /**
     * Query data.
     *
     * @param LanguageModel $model
     *
     * @return array
     */
    public function query(LanguageModel $model): iterable
    {
        $this->model = $model;
        
        // Konvertieren der Einstellungen zu einem JSON-String bereits hier
        $settingsJson = json_encode($model->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

        return [
            'model' => $model,
            'settings' => $model->settings,
            'settingsJson' => $settingsJson, // Neues Feld für den JSON-String
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Edit Model Settings: ' . $this->model->label;
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Configure model parameters and settings';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [

            Link::make('Back to Models')
                ->icon('arrow-left')
                ->route('platform.modelsettings.models'),

            Button::make('Save')
                ->icon('save')
                ->method('save'),

        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
                Layout::rows([
                    TextArea::make('settingsJson') // Verwende das neue Feld statt model.settings
                        ->title('Model Settings (JSON)')
                        ->help('Configure the settings for this model in JSON format')
                        ->rows(10)
                        ->required()
                        ->style('min-width: 100%; resize: vertical; font-family: monospace;'),
                ]),

        Layout::view('platform.info.modelsettings-info'),
        ];
    }

    /**
     * Save model settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request)
    {
        try {
            // Get the settings from the request - Verwende den neuen Feldnamen
            $settings = $request->input('settingsJson');
            
            // Decode the JSON to validate it
            $jsonSettings = json_decode($settings, true);
            
            // Check if JSON is valid
            if (json_last_error() !== JSON_ERROR_NONE) {
                Toast::error('Ungültiges JSON-Format. Bitte überprüfen Sie Ihre Eingabe.');
                return back()->withInput();
            }
            
            // Update the model settings
            $this->model->settings = $jsonSettings;
            $this->model->save();
            
            Toast::info('Modell-Einstellungen wurden erfolgreich gespeichert.');
            
        } catch (\Exception $e) {
            Log::error('Fehler beim Speichern der Modell-Einstellungen: ' . $e->getMessage());
            Toast::error('Ein Fehler ist beim Speichern aufgetreten: ' . $e->getMessage());
            return back()->withInput();
        }
        
        return redirect()->route('platform.modelsettings.models.settings', $this->model);
    }

}
