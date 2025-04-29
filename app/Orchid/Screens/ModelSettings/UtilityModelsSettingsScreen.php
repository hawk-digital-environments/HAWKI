<?php

namespace App\Orchid\Screens\ModelSettings;

use App\Models\LanguageModel;
use App\Models\SystemPrompt;
use App\Services\SettingsService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Fields\Group;


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
            'prompts' => [
                'default_model' => [
                    'de' => SystemPrompt::getPrompt('default_model', 'de') ?? 'Default German prompt',
                    'en' => SystemPrompt::getPrompt('default_model', 'en') ?? 'Default English prompt',
                ],
                'title_generator' => [
                    'de' => SystemPrompt::getPrompt('title_generator', 'de') ?? 'Title Generator German prompt',
                    'en' => SystemPrompt::getPrompt('title_generator', 'en') ?? 'Title Generator English prompt',
                ],
                'prompt_improver' => [
                    'de' => SystemPrompt::getPrompt('prompt_improver', 'de') ?? 'Prompt Improver German prompt',
                    'en' => SystemPrompt::getPrompt('prompt_improver', 'en') ?? 'Prompt Improver English prompt',
                ],
                'summarizer' => [
                    'de' => SystemPrompt::getPrompt('summarizer', 'de') ?? 'Summarizer German prompt',
                    'en' => SystemPrompt::getPrompt('summarizer', 'en') ?? 'Summarizer English prompt',
                ],
            ],
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
            Button::make('Import from Config')
                ->icon('cloud-download')
                ->method('importFromConfig')
                ->confirm('Are you sure? This will migrate prompts from the JSON files to the database.'),

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
            // Intro view
            Layout::view('orchid.utility-models.info'),



            // Default System Models
            Layout::block([
                Layout::rows([  
                    Select::make('defaultModel')
                            ->title('Default Model')
                            ->options($this->getAllAvailableModels())
                            ->value($this->query()['defaultModel'])
                            ->help('This model is used by default before the user chooses their desired model.')
                            ->horizontal(),
                ]),

                Layout::rows([
                    TextArea::make('prompts.default_model.de')
                        ->title('Default System Prompt (de_DE)')
                        ->value($this->query()['prompts']['default_model']['de'])
                        ->required()
                        ->rows(8)
                        ->style('min-width: 110%;')
                        ->horizontal(),

                    TextArea::make('prompts.default_model.en')
                        ->title('Default System Prompt (en_US)')
                        ->value($this->query()['prompts']['default_model']['en'])
                        ->required()
                        ->rows(8)
                        ->style('min-width: 110%;')
                        ->horizontal(),
                ]),
            ])
            ->vertical()
            ->title('Default System Settings')
            ->description('Sets the default system prompt and chat model before the user changes it.')
            ->commands(
                Button::make('Save System Settings')
                    ->icon('save')
                    ->method('saveSettings')
            ),

            // Title Generator Prompts
            Layout::block([
                Layout::rows([

                    Select::make('system_models.title_generator')
                        ->title('Title Generator Model')
                        ->options($this->getAllAvailableModels())
                        ->value($this->query()['system_models']['title_generator'] ?? null)
                        ->help('Model used for generating titles automatically.')
                        ->horizontal(),

                    TextArea::make('prompts.title_generator.de')
                        ->title('Title Generator Prompt (de_DE)')
                        ->value($this->query()['prompts']['title_generator']['de'])
                        ->help('Prompt used for generating titles automatically.')
                        ->horizontal()
                        ->required()
                        ->rows(4)
                        ->style('min-width: 110%;'),

                    TextArea::make('prompts.title_generator.en')
                        ->title('Title Generator Prompt (en_US)')
                        ->value($this->query()['prompts']['title_generator']['en'])
                        ->help('Prompt used for generating titles automatically.')
                        ->horizontal()
                        ->required()
                        ->rows(4)
                        ->style('min-width: 110%;'),
                ]),
            ])
            ->vertical()
            ->title('Title Generator Settings')
            ->description('Used to automatically generate titles for chats and responses.')
            ->commands(
                Button::make('Save Title Generator Settings')
                    ->icon('save')
                    ->method('saveSettings')
            ),

            // Prompt Improver Prompts
            Layout::block([
                Layout::rows([

                    Select::make('system_models.prompt_improver')
                        ->title('Prompt Improver Model')
                        ->options($this->getAllAvailableModels())
                        ->value($this->query()['system_models']['prompt_improver'] ?? null)
                        ->help('Model used for improving and refining user prompts.')
                        ->horizontal(),

                    TextArea::make('prompts.prompt_improver.de')
                        ->title('Prompt Improver Prompt (de_DE)')
                        ->value($this->query()['prompts']['prompt_improver']['de'])
                        ->help('Prompt used for improving and refining user prompts.')
                        ->horizontal()
                        ->required()
                        ->rows(4)
                        ->style('min-width: 110%;'),

                    TextArea::make('prompts.prompt_improver.en')
                        ->title('Prompt Improver Prompt (en_US)')
                        ->value($this->query()['prompts']['prompt_improver']['en'])
                        ->help('Prompt used for improving and refining user prompts.')
                        ->horizontal()
                        ->required()
                        ->rows(4)
                        ->style('min-width: 110%;'),
                ]),
            ])
            ->vertical()
            ->title('Prompt Improver Prompts')
            ->description('Used to refine and enhance user prompts to get better results.')
            ->commands(
                Button::make('Save Prompt Improver Settings')
                    ->icon('save')
                    ->method('saveSettings')
            ),

            // Summarizer Prompts
            Layout::block([
                Layout::rows([

                    Select::make('system_models.summarizer')
                        ->title('Summarizer Model')
                        ->options($this->getAllAvailableModels())
                        ->value($this->query()['system_models']['summarizer'] ?? null)
                        ->help('Model used for summarizing content.')
                        ->horizontal(),

                    TextArea::make('prompts.summarizer.de')
                        ->title('Summarizer Prompt (de_DE)')
                        ->value($this->query()['prompts']['summarizer']['de'])
                        ->help('Prompt used for summarizing content.')
                        ->horizontal()
                        ->required()
                        ->rows(4)
                        ->style('min-width: 110%;'),

                    TextArea::make('prompts.summarizer.en')
                        ->title('Summarizer Prompt (en_US)')
                        ->value($this->query()['prompts']['summarizer']['en'])
                        ->help('Prompt used for summarizing content.')
                        ->horizontal()
                        ->required()
                        ->rows(4)
                        ->style('min-width: 110%;'),
                ]),
            ])
            ->vertical()
            ->title('Summarizer Prompts')
            ->description('Used to create summaries of longer texts or conversations.')
            ->commands(
                Button::make('Save Summarizer Settings')
                    ->icon('save')
                    ->method('saveSettings')
            ),
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
        
        // Save system prompts to database
        $prompts = $request->input('prompts', []);
        foreach ($prompts as $modelType => $languages) {
            foreach ($languages as $lang => $promptText) {
                SystemPrompt::setPrompt($modelType, $lang, $promptText);
            }
        }
        
        Toast::success('Utility model settings have been saved.');
        return redirect()->route('platform.modelsettings.utilitymodels');
    }
    
    /**
     * Import prompt settings from config files to database.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function importFromConfig()
    {
        Log::info('=== Starting prompt import from config files ===');
        $importedCount = 0;
        
        // Pfad zu den JSON-Dateien
        $dePromptPath = resource_path('language/prompts_de_DE.json');
        $enPromptPath = resource_path('language/prompts_en_US.json');
        
        Log::info("Looking for German prompts file at: $dePromptPath");
        Log::info("Looking for English prompts file at: $enPromptPath");
        
        // Mapping der JSON-Schlüssel zu unseren model_types definieren
        // KORRIGIERT: Schlüssel sind jetzt die Namen aus der JSON-Datei, Werte sind die model_types
        $mappings = [
            'Default_Prompt' => 'default_model',
            'Name_Prompt' => 'title_generator',
            'Improvement_Prompt' => 'prompt_improver',
            'Summery_Prompt' => 'summarizer',
        ];
        
        Log::info('Using the following key mappings:', $mappings);
        
        // Deutsche Prompts importieren
        if (file_exists($dePromptPath)) {
            Log::info("German prompts file found. Attempting to read contents.");
            $dePrompts = json_decode(file_get_contents($dePromptPath), true);
            
            if ($dePrompts && is_array($dePrompts)) {
                Log::info("German prompts file successfully parsed. Found keys: " . implode(', ', array_keys($dePrompts)));
                
                foreach ($mappings as $jsonKey => $modelType) {
                    if (isset($dePrompts[$jsonKey])) {
                        try {
                            Log::info("Processing German prompt for key '$jsonKey' -> model_type '$modelType'");
                            
                            // Substring des Prompts für das Log (zu lang für vollständiges Logging)
                            $promptSubstring = substr($dePrompts[$jsonKey], 0, 50) . (strlen($dePrompts[$jsonKey]) > 50 ? '...' : '');
                            Log::info("German prompt content (truncated): $promptSubstring");
                            
                            SystemPrompt::setPrompt($modelType, 'de', $dePrompts[$jsonKey]);
                            $importedCount++;
                            Log::info("Successfully imported German prompt for '$jsonKey'");
                        } catch (\Exception $e) {
                            Log::error("Failed to import German prompt for '$jsonKey': " . $e->getMessage());
                            Log::error($e->getTraceAsString());
                        }
                    } else {
                        Log::warning("Key '$jsonKey' not found in German prompts file");
                    }
                }
            } else {
                Log::error("Failed to parse German prompts file. Check if it contains valid JSON.");
            }
        } else {
            Log::warning("German prompts file not found at: $dePromptPath");
        }
        
        // Englische Prompts importieren
        if (file_exists($enPromptPath)) {
            Log::info("English prompts file found. Attempting to read contents.");
            $enPrompts = json_decode(file_get_contents($enPromptPath), true);
            
            if ($enPrompts && is_array($enPrompts)) {
                Log::info("English prompts file successfully parsed. Found keys: " . implode(', ', array_keys($enPrompts)));
                
                foreach ($mappings as $jsonKey => $modelType) {
                    if (isset($enPrompts[$jsonKey])) {
                        try {
                            Log::info("Processing English prompt for key '$jsonKey' -> model_type '$modelType'");
                            
                            // Substring des Prompts für das Log (zu lang für vollständiges Logging)
                            $promptSubstring = substr($enPrompts[$jsonKey], 0, 50) . (strlen($enPrompts[$jsonKey]) > 50 ? '...' : '');
                            Log::info("English prompt content (truncated): $promptSubstring");
                            
                            SystemPrompt::setPrompt($modelType, 'en', $enPrompts[$jsonKey]);
                            $importedCount++;
                            Log::info("Successfully imported English prompt for '$jsonKey'");
                        } catch (\Exception $e) {
                            Log::error("Failed to import English prompt for '$jsonKey': " . $e->getMessage());
                            Log::error($e->getTraceAsString());
                        }
                    } else {
                        Log::warning("Key '$jsonKey' not found in English prompts file");
                    }
                }
            } else {
                Log::error("Failed to parse English prompts file. Check if it contains valid JSON.");
            }
        } else {
            Log::warning("English prompts file not found at: $enPromptPath");
        }
        
        Log::info("=== Import completed. Total prompts imported: $importedCount ===");
        
        if ($importedCount > 0) {
            Toast::success("$importedCount prompts successfully imported from configuration files.");
        } else {
            Toast::error("No prompts found in configuration files or they couldn't be imported.");
        }
        
        return redirect()->route('platform.modelsettings.utilitymodels');
    }
}
