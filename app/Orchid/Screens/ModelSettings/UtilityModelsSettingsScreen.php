<?php

namespace App\Orchid\Screens\ModelSettings;

use App\Models\LanguageModel;
use App\Services\SettingsService;

use Illuminate\Http\Request;

use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class UtilityModelsSettingsScreen extends Screen
{
    protected $settings;
    
    public function __construct(SettingsService $settings)
    {
        $this->settings = $settings;
    }
    
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'defaultModel' => $this->settings->get('default_language_model'),
            'system_models' => [
                'title_generator' => $this->settings->get('system_model_title_generator'),
                'prompt_improver' => $this->settings->get('system_model_prompt_improver'),
                'summarizer' => $this->settings->get('system_model_summarizer'),
            ],
            'available_models' => $this->getAllAvailableModels(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Utility Models Settings';
    }
    /**
     * Display header description.
     */
     public function description(): ?string
    {
        return 'Configure the default language models used by the system.';
    }
    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            //Button::make('Import from Config')
            //    ->icon('cloud-download')
            //    ->method('importFromConfig')
            //    ->confirm('Are you sure? This will reset any unsaved changes.'),

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
        return [
            Layout::rows([
                Select::make('defaultModel')
                    ->title('Default Model')
                    ->options($this->getAllAvailableModels())
                    ->value($this->query()['defaultModel'])
                    ->help('This model is used by default before the user chooses their desired model.'),
                
                Select::make('system_models.title_generator')
                    ->title('Title Generator Model')
                    ->options($this->getAllAvailableModels())
                    ->value($this->query()['system_models']['title_generator'] ?? null)
                    ->help('Model used for generating titles automatically.'),
                
                Select::make('system_models.prompt_improver')
                    ->title('Prompt Improver Model')
                    ->options($this->getAllAvailableModels())
                    ->value($this->query()['system_models']['prompt_improver'] ?? null)
                    ->help('Model used for improving and refining user prompts.'),
                
                Select::make('system_models.summarizer')
                    ->title('Summarizer Model')
                    ->options($this->getAllAvailableModels())
                    ->value($this->query()['system_models']['summarizer'] ?? null)
                    ->help('Model used for summarizing content.'),
            ])->title('System Utility Models'),
            
            Layout::view('orchid.utility-models.info'),
        ];
    }
    
    /**
     * Get all available models from the database.
     *
     * @return array
     */
    private function getAllAvailableModels(): array
    {
        // Get active and visible models from the Language_models table
        return LanguageModel::where('is_active', true)
            ->where('is_visible', true)
            ->orderBy('display_order')
            ->pluck('label', 'model_id')
            ->toArray();
    }
    
    /**
     * Save utility models settings to the app_settings table.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveSettings(Request $request)
    {
        // Update default model
        $defaultModel = $request->input('defaultModel');
        if ($defaultModel) {
            $this->settings->set(
                'default_language_model', 
                $defaultModel, 
                'models', 
                'string', 
                'Default language model for the application'
            );
        }
        
        // Update system models
        $systemModels = $request->input('system_models', []);
        foreach ($systemModels as $key => $value) {
            if ($value) {
                $this->settings->set(
                    'system_model_' . $key, 
                    $value, 
                    'models', 
                    'string', 
                    ucfirst(str_replace('_', ' ', $key)) . ' model for system operations'
                );
            }
        }
        
        Toast::success('Utility model settings have been saved.');
        return redirect()->route('platform.modelsettings.utilitymodels');
    }
    
    /**
     * Import current settings from the database.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function importFromConfig()
    {
        // For consistency, keep this method but now it just refreshes the page
        // to show current settings from the database
        Toast::info('Current settings have been loaded.');
        return redirect()->route('platform.modelsettings.utilitymodels');
    }
}
