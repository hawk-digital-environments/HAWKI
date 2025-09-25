<?php

namespace App\Orchid\Screens\ModelSettings;

use App\Models\AppSystemPrompt;
use App\Models\AiModel;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
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
            'prompts' => [
                'default_system_prompt' => [
                    'de_DE' => AppSystemPrompt::getPrompt('default_system_prompt', 'de_DE') ?? 'Default German prompt',
                    'en_US' => AppSystemPrompt::getPrompt('default_system_prompt', 'en_US') ?? 'Default English prompt',
                ],
                'title_generation_prompt' => [
                    'de_DE' => AppSystemPrompt::getPrompt('title_generation_prompt', 'de_DE') ?? 'Title Generation German prompt',
                    'en_US' => AppSystemPrompt::getPrompt('title_generation_prompt', 'en_US') ?? 'Title Generation English prompt',
                ],
                'prompt_improvement_prompt' => [
                    'de_DE' => AppSystemPrompt::getPrompt('prompt_improvement_prompt', 'de_DE') ?? 'Prompt Improver German prompt',
                    'en_US' => AppSystemPrompt::getPrompt('prompt_improvement_prompt', 'en_US') ?? 'Prompt Improver English prompt',
                ],
                'summary_prompt' => [
                    'de_DE' => AppSystemPrompt::getPrompt('summary_prompt', 'de_DE') ?? 'Summarizer German prompt',
                    'en_US' => AppSystemPrompt::getPrompt('summary_prompt', 'en_US') ?? 'Summarizer English prompt',
                ],
            ],
        ];
    }

    public function name(): ?string
    {
        return 'Utility Models Settings';
    }

    public function description(): ?string
    {
        return 'Configure the default language models used by the system.';
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Load Defaults')
                ->icon('arrow-repeat')
                ->method('runSystemPromptSeeder')
                ->confirm('Are you sure? This will load the default system prompts from the seeder into the database.'),

            Button::make('Save')
                ->icon('save')
                ->method('saveSettings'),
        ];
    }

    public function layout(): iterable
    {
        return [
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
                    TextArea::make('prompts.default_system_prompt.de_DE')
                        ->title('Default System Prompt (de_DE)')
                        ->value($this->query()['prompts']['default_system_prompt']['de_DE'])
                        ->required()
                        ->rows(8)
                        ->style('min-width: 110%;')
                        ->horizontal(),

                    TextArea::make('prompts.default_system_prompt.en_US')
                        ->title('Default System Prompt (en_US)')
                        ->value($this->query()['prompts']['default_system_prompt']['en_US'])
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
                        ->title('Title Generation Model')
                        ->options($this->getAllAvailableModels())
                        ->value($this->query()['system_models']['title_generator'] ?? null)
                        ->help('Model used for generating titles automatically.')
                        ->horizontal(),

                    TextArea::make('prompts.title_generation_prompt.de_DE')
                        ->title('Title Generation Prompt (de_DE)')
                        ->value($this->query()['prompts']['title_generation_prompt']['de_DE'])
                        ->horizontal()
                        ->required()
                        ->rows(4)
                        ->style('min-width: 110%;'),

                    TextArea::make('prompts.title_generation_prompt.en_US')
                        ->title('Title Generation Prompt (en_US)')
                        ->value($this->query()['prompts']['title_generation_prompt']['en_US'])
                        ->horizontal()
                        ->required()
                        ->rows(4)
                        ->style('min-width: 110%;'),
                ]),
            ])
                ->vertical()
                ->title('Title Generation Settings')
                ->description('Used to automatically generate titles for chats and responses.')
                ->commands(
                    Button::make('Save Title Generation Settings')
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

                    TextArea::make('prompts.prompt_improvement_prompt.de_DE')
                        ->title('Prompt Improver Prompt (de_DE)')
                        ->value($this->query()['prompts']['prompt_improvement_prompt']['de_DE'])
                        ->horizontal()
                        ->required()
                        ->rows(4)
                        ->style('min-width: 110%;'),

                    TextArea::make('prompts.prompt_improvement_prompt.en_US')
                        ->title('Prompt Improver Prompt (en_US)')
                        ->value($this->query()['prompts']['prompt_improvement_prompt']['en_US'])
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

                    TextArea::make('prompts.summary_prompt.de_DE')
                        ->title('Summarizer Prompt (de_DE)')
                        ->value($this->query()['prompts']['summary_prompt']['de_DE'])
                        ->horizontal()
                        ->required()
                        ->rows(4)
                        ->style('min-width: 110%;'),

                    TextArea::make('prompts.summary_prompt.en_US')
                        ->title('Summarizer Prompt (en_US)')
                        ->value($this->query()['prompts']['summary_prompt']['en_US'])
                        ->horizontal()
                        ->required()
                        ->rows(4)
                        ->style('min-width: 110%;'),
                ]),
            ])
                ->vertical()
                ->title('Summarizer Prompts')
                ->description('Used to create summaries of longer texts or conversations for exports.')
                ->commands(
                    Button::make('Save Summarizer Settings')
                        ->icon('save')
                        ->method('saveSettings')
                ),
        ];
    }

    private function getAllAvailableModels(): array
    {
        // Get active and visible models from the Language_models table with active providers
        return AiModel::where('is_active', true)
            // ->where('is_visible', true)
            ->whereHas('provider', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('display_order')
            ->pluck('label', 'model_id')
            ->toArray();
    }

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
                    'system_model_'.$key,
                    $value,
                    'models',
                    'string',
                    ucfirst(str_replace('_', ' ', $key)).' model for system operations'
                );
            }
        }

        // Save system prompts to database
        $prompts = $request->input('prompts', []);
        foreach ($prompts as $modelType => $languages) {
            foreach ($languages as $lang => $promptText) {
                AppSystemPrompt::setPrompt($modelType, $lang, $promptText);
            }
        }

        Toast::success('Utility model settings have been saved.');

        return redirect()->route('platform.models.utility');
    }

    public function importFromConfig()
    {
        Log::info('=== Starting prompt import from config files ===');
        $importedCount = 0;

        // Path to JSON files
        $dePromptPath = resource_path('language/prompts_de_DE.json');
        $enPromptPath = resource_path('language/prompts_en_US.json');

        Log::info("Looking for German prompts file at: $dePromptPath");
        Log::info("Looking for English prompts file at: $enPromptPath");

        // Define mapping of JSON keys to our model_types
        $mappings = [
            'Default_Prompt' => 'default_system_prompt',
            'Name_Prompt' => 'title_generator_prompt',
            'Improvement_Prompt' => 'prompt_improver_prompt',
            'Summery_Prompt' => 'summarizer_prompt',
        ];

        Log::info('Using the following key mappings:', $mappings);

        // Import German prompts
        if (file_exists($dePromptPath)) {
            Log::info('German prompts file found. Attempting to read contents.');
            $dePrompts = json_decode(file_get_contents($dePromptPath), true);

            if ($dePrompts && is_array($dePrompts)) {
                Log::info('German prompts file successfully parsed. Found keys: '.implode(', ', array_keys($dePrompts)));

                foreach ($mappings as $jsonKey => $modelType) {
                    if (isset($dePrompts[$jsonKey])) {
                        try {
                            Log::info("Processing German prompt for key '$jsonKey' -> model_type '$modelType'");

                            // Substring of the prompt for logging (too long for full logging)
                            $promptSubstring = substr($dePrompts[$jsonKey], 0, 50).(strlen($dePrompts[$jsonKey]) > 50 ? '...' : '');
                            Log::info("German prompt content (truncated): $promptSubstring");

                            AppSystemPrompt::setPrompt($modelType, 'de_DE', $dePrompts[$jsonKey]);
                            $importedCount++;
                            Log::info("Successfully imported German prompt for '$jsonKey'");
                        } catch (\Exception $e) {
                            Log::error("Failed to import German prompt for '$jsonKey': ".$e->getMessage());
                            Log::error($e->getTraceAsString());
                        }
                    } else {
                        Log::warning("Key '$jsonKey' not found in German prompts file");
                    }
                }
            } else {
                Log::error('Failed to parse German prompts file. Check if it contains valid JSON.');
            }
        } else {
            Log::warning("German prompts file not found at: $dePromptPath");
        }

        // Import English prompts
        if (file_exists($enPromptPath)) {
            Log::info('English prompts file found. Attempting to read contents.');
            $enPrompts = json_decode(file_get_contents($enPromptPath), true);

            if ($enPrompts && is_array($enPrompts)) {
                Log::info('English prompts file successfully parsed. Found keys: '.implode(', ', array_keys($enPrompts)));

                foreach ($mappings as $jsonKey => $modelType) {
                    if (isset($enPrompts[$jsonKey])) {
                        try {
                            Log::info("Processing English prompt for key '$jsonKey' -> model_type '$modelType'");

                            // Use the correct language code 'en_US'
                            AppSystemPrompt::setPrompt($modelType, 'en_US', $enPrompts[$jsonKey]);
                            $importedCount++;
                            Log::info("Successfully imported English prompt for '$jsonKey'");
                        } catch (\Exception $e) {
                            Log::error("Failed to import English prompt for '$jsonKey': ".$e->getMessage());
                            Log::error($e->getTraceAsString());
                        }
                    } else {
                        Log::warning("Key '$jsonKey' not found in English prompts file");
                    }
                }
            } else {
                Log::error('Failed to parse English prompts file. Check if it contains valid JSON.');
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

        return redirect()->route('platform.models.utility');
    }

    public function runSystemPromptSeeder()
    {
        try {
            Log::info('=== Starte AppSystemPromptSeeder ===');

            $output = Artisan::call('db:seed', [
                '--class' => 'AppSystemPromptSeeder',
            ]);

            Log::info('Seeder-Ausgabe: '.Artisan::output());
            Toast::success('System Prompts wurden erfolgreich aus dem Seeder geladen.');
        } catch (\Exception $e) {
            Log::error('Fehler beim Ausführen des AppSystemPromptSeeders: '.$e->getMessage());
            Toast::error('Fehler beim Ausführen des Seeders: '.$e->getMessage());
        }

        return redirect()->route('platform.models.utility');
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.modelsettings.utilitymodels',
        ];
    }
}
