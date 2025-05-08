<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppLocalizedText;
use App\Models\AppSystemText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Fields\Quill;

use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;


class TextsSettingsScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Request $request): iterable
    {
        // Lokalisierte Texte laden
        $contentKeys = AppLocalizedText::select('content_key')
            ->distinct()
            ->orderBy('content_key')
            ->pluck('content_key')
            ->toArray();
        
        $localizedTexts = [];
        foreach ($contentKeys as $key) {
            $deText = AppLocalizedText::where('content_key', $key)
                ->where('language', 'de_DE')
                ->first();
            
            $enText = AppLocalizedText::where('content_key', $key)
                ->where('language', 'en_US')
                ->first();
            
            $localizedTexts[$key] = [
                'de_DE' => $deText ? $deText->toArray() : null,
                'en_US' => $enText ? $enText->toArray() : null,
            ];
        }
        
        // System Texte laden
        $contentKeys = AppSystemText::select('content_key')
            ->distinct()
            ->orderBy('content_key')
            ->pluck('content_key')
            ->toArray();
            
        $systemTexts = [];
        foreach ($contentKeys as $key) {
            $deText = AppSystemText::where('content_key', $key)
                ->where('language', 'de_DE')
                ->first();
            
            $enText = AppSystemText::where('content_key', $key)
                ->where('language', 'en_US')
                ->first();
            
            $systemTexts[$key] = [
                'de_DE' => $deText ? $deText->content : '',
                'en_US' => $enText ? $enText->content : '',
            ];
        }
        
        return [
            'localizedTexts' => $localizedTexts,
            'systemTexts' => $systemTexts
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Text Localization Settings';
    }
    public function description(): ?string
    {
        return 'Configure the default texts used by the system.';
    }
    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Load Defaults')
                ->icon('arrow-repeat')
                ->method('loadDefaults')
                ->confirm('Are you sure? This will execute the AppLocalizedTextSeeder and load the default texts.'),

            Button::make('Save')
                ->icon('save')
                ->method('saveChanges'),
            ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        // Build the layouts for each tab
        $localizedTextsContent = $this->buildLocalizedTextsLayout();
        $systemTextsContent = $this->buildSystemTextsLayout();
        
        return [
            Layout::tabs([
                'Localized Text' => $localizedTextsContent,
                'System Text' => $systemTextsContent,
            ]),
        ];
    }
    
    /**
     * Build the layout for localized texts tab
     * 
     * @return array
     */
    protected function buildLocalizedTextsLayout(): array
    {
        $layouts = [];

        // Für jeden lokalisierten Text einen Block erstellen
        $localizedTexts = $this->query(request())['localizedTexts'];
        foreach ($localizedTexts as $key => $texts) {
            $deText = $texts['de_DE'];
            $enText = $texts['en_US'];
            
            $layouts[] = Layout::block([
                Layout::rows([
                    // German content
                    TextArea::make("texts[{$key}][de_DE]")
                        ->title('German Content (de_DE)')
                        ->value($deText ? $deText['content'] : '')
                        ->rows(5)
                        ->style('min-width: 100%; resize: both;'),
                        
                    // English content
                    TextArea::make("texts[{$key}][en_US]")
                        ->title('English Content (en_US)')
                        ->value($enText ? $enText['content'] : '')
                        ->rows(5)
                        ->style('min-width: 100%; resize: both;'),
                ]),
            ])
            ->vertical()
            ->title($key)
            ->description('Preview: ' . Str::limit(strip_tags($deText ? $deText['content'] : ''), 100))
            ->commands([
                Button::make('Reset')
                    ->icon('arrow-repeat')
                    ->confirm('Are you sure you want to reset this localized text to its default value?')
                    ->method('resetLocalizedText', ['key' => $key]),

                Button::make('Save')
                    ->icon('save')
                    ->method('saveLocalizedTexts'),
            ]);
        }
        
        // Akkordeon für neue Texte nach unten verschoben
        $layouts[] = Layout::accordion([
            'New Localized Text' => [
                Layout::rows([
                    Input::make('new_key')
                        ->title('Content Key')
                        ->placeholder('Enter a content key (e.g. guidelines, welcome_text)'),

                    // German content field
                    TextArea::make('new_content_de_DE')
                        ->title('German Content (de_DE)')
                        ->placeholder('Enter HTML content in German')
                        ->rows(10),
                        
                    // English content field    
                    TextArea::make('new_content_en_US')
                        ->title('English Content (en_US)')
                        ->placeholder('Enter HTML content in English')
                        ->rows(10),
                    
                    Button::make('Add New Content')
                        ->icon('plus')
                        ->method('addNewLocalizedText')
                        ->class('btn btn-success'),
                ])
            ]
        ])->open([]);

        return $layouts;
    }

    /**
     * Build the layout for system texts tab
     * 
     * @return array
     */
    protected function buildSystemTextsLayout(): array
    {
        $layouts = [];
        
        // Suche-Feld
        $layouts[] = Layout::rows([
            Input::make('search_system_text')
                ->title('Search')
                ->placeholder('Enter text or key to search')
                ->help('Filter the system texts list')
                ->method('applySearch'),
        ]);
        
        // System Texte in Blöcke darstellen
        $systemTexts = $this->query(request())['systemTexts'];
        
        // Sortierung alphabetisch nach key
        ksort($systemTexts);
        
        foreach ($systemTexts as $key => $texts) {
            // Suchfilter anwenden, wenn vorhanden
            $searchTerm = request('search_system_text');
            if (!empty($searchTerm) && 
                strpos(strtolower($key), strtolower($searchTerm)) === false && 
                strpos(strtolower($texts['de_DE'] ?? ''), strtolower($searchTerm)) === false && 
                strpos(strtolower($texts['en_US'] ?? ''), strtolower($searchTerm)) === false) {
                continue;
            }
            
            $layouts[] = Layout::block([
                Layout::rows([
                    // German content
                    TextArea::make("system_texts[{$key}][de_DE]")
                        ->title('German (de_DE)')
                        ->value($texts['de_DE'])
                        ->rows(2)
                        ->style('min-width: 100%;'),
                        
                    // English content
                    TextArea::make("system_texts[{$key}][en_US]")
                        ->title('English (en_US)')
                        ->value($texts['en_US'])
                        ->rows(2)
                        ->style('min-width: 100%;'),
                ]),
            ])
            ->vertical()
            ->title($key)
            ->commands([
                Button::make('Reset')
                    ->icon('arrow-repeat')
                    ->confirm('Are you sure you want to reset this system text to its default value?')
                    ->method('resetSystemText', ['key' => $key]),
                    
                Button::make('Save')
                    ->icon('save')
                    ->method('saveSystemTexts'),
            ]);
        }
        
        // Add new system text form
        $layouts[] = Layout::accordion([
            'Add New System Text' => [
                Layout::rows([
                    Input::make('new_system_key')
                        ->title('Text Key')
                        ->placeholder('Enter a key (e.g. Guidelines, Login, etc.)'),
                        
                    TextArea::make('new_system_de_DE')
                        ->title('German Text (de_DE)')
                        ->placeholder('Enter German text')
                        ->rows(3),
                        
                    TextArea::make('new_system_en_US')
                        ->title('English Text (en_US)')
                        ->placeholder('Enter English text')
                        ->rows(3),
                        
                    Button::make('Add New System Text')
                        ->icon('plus')
                        ->method('addNewSystemText')
                        ->class('btn btn-success'),
                ]),
            ],
        ]);
        
        return $layouts;
    }

    /**
     * Save changes to localized texts.
     */
    public function saveLocalizedTexts(Request $request)
    {
        $texts = $request->get('texts', []);
        $count = 0;
        
        if ($texts) {
            // Sammle alle Einträge, die wirklich geändert wurden
            $changedEntries = [];
            
            foreach ($texts as $key => $languages) {
                foreach ($languages as $language => $content) {
                    if (!empty($content)) {
                        // Prüfe, ob der Inhalt tatsächlich geändert wurde
                        $existingText = AppLocalizedText::where('content_key', $key)
                            ->where('language', $language)
                            ->first();
                        
                        // Normalisiere die Texte für einen zuverlässigen Vergleich
                        $normalizedNewContent = $this->normalizeContent($content);
                        $normalizedExistingContent = $existingText ? $this->normalizeContent($existingText->content) : null;
                        
                        if (!$existingText || $normalizedExistingContent !== $normalizedNewContent) {
                            // Speichere für das spätere Update
                            $changedEntries[] = [
                                'key' => $key,
                                'language' => $language,
                                'content' => $content,
                                'exists' => (bool)$existingText
                            ];
                        }
                    }
                }
            }
            
            // Führe nur für die wirklich geänderten Einträge Updates durch
            foreach ($changedEntries as $entry) {
                AppLocalizedText::updateOrCreate(
                    [
                        'content_key' => $entry['key'],
                        'language' => $entry['language']
                    ],
                    [
                        'content' => $entry['content']
                    ]
                );
                $count++;
                
                Log::info(
                    'Updated localized text', 
                    [
                        'key' => $entry['key'], 
                        'language' => $entry['language'],
                        'action' => $entry['exists'] ? 'update' : 'create'
                    ]
                );
            }
            
            if ($count > 0) {
                Toast::info("$count localized text entries have been updated");
            } else {
                Toast::info("No changes detected");
            }
        }
        
        return;
    }

    /**
     * Save system text changes.
     */
    public function saveSystemTexts(Request $request)
    {
        $texts = $request->get('system_texts', []);
        $count = 0;
        
        if ($texts) {
            // Sammle alle Einträge, die wirklich geändert wurden
            $changedEntries = [];
            
            foreach ($texts as $key => $languages) {
                foreach ($languages as $language => $content) {
                    if (!empty($content)) {
                        $existingText = AppSystemText::where('content_key', $key)
                            ->where('language', $language)
                            ->first();
                        
                        // Normalisiere die Texte für einen zuverlässigen Vergleich
                        $normalizedNewContent = $this->normalizeContent($content);
                        $normalizedExistingContent = $existingText ? $this->normalizeContent($existingText->content) : null;
                        
                        if (!$existingText || $normalizedExistingContent !== $normalizedNewContent) {
                            // Speichere für das spätere Update
                            $changedEntries[] = [
                                'key' => $key,
                                'language' => $language,
                                'content' => $content,
                                'exists' => (bool)$existingText
                            ];
                        }
                    }
                }
            }
            
            // Führe nur für die wirklich geänderten Einträge Updates durch
            foreach ($changedEntries as $entry) {
                AppSystemText::updateOrCreate(
                    [
                        'content_key' => $entry['key'],
                        'language' => $entry['language']
                    ],
                    [
                        'content' => $entry['content']
                    ]
                );
                $count++;
                
                Log::info(
                    'Updated system text', 
                    [
                        'key' => $entry['key'], 
                        'language' => $entry['language'],
                        'action' => $entry['exists'] ? 'update' : 'create'
                    ]
                );
            }
            
            if ($count > 0) {
                Toast::info("$count system text entries have been updated");
            } else {
                Toast::info("No changes detected");
            }
        }
        
        return;
    }
    
    /**
     * Normalize content for reliable comparison
     * 
     * @param string $content
     * @return string
     */
    protected function normalizeContent(string $content): string
    {
        // Trim whitespace and normalize line endings
        $normalized = trim($content);
        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);
        
        // Normalize common HTML entities
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5);
        
        return $normalized;
    }

    /**
     * Add a new localized text for all supported languages.
     */
    public function addNewLocalizedText(Request $request)
    {
        $key = $request->get('new_key');
        $deContent = $request->get('new_content_de_DE');
        $enContent = $request->get('new_content_en_US');
        
        if (empty($key) || empty($deContent) || empty($enContent)) {
            Toast::error('Content key and both language contents are required');
            return redirect()->route('platform.settings.texts');
        }
        
        // Standardize key (snake_case)
        $key = Str::snake($key);
        
        // Create entries for both languages
        AppLocalizedText::updateOrCreate(
            ['content_key' => $key, 'language' => 'de_DE'],
            ['content' => $deContent]
        );
        
        AppLocalizedText::updateOrCreate(
            ['content_key' => $key, 'language' => 'en_US'],
            ['content' => $enContent]
        );
        
        Toast::info("Content key '$key' has been created with both language versions.");
        
        return redirect()->route('platform.settings.texts');
    }

    /**
     * Add a new system text.
     */
    public function addNewSystemText(Request $request)
    {
        $key = $request->get('new_system_key');
        $deText = $request->get('new_system_de_DE');
        $enText = $request->get('new_system_en_US');
        
        if (empty($key) || empty($deText) || empty($enText)) {
            Toast::error('Key and both language texts are required');
            return redirect()->route('platform.settings.texts');
        }
        
        // Create entries for both languages
        AppSystemText::setText($key, 'de_DE', $deText);
        AppSystemText::setText($key, 'en_US', $enText);
        
        Toast::info("System text '$key' has been created with both language versions.");
        
        return redirect()->route('platform.settings.texts');
    }

    /**
     * Reset a localized text to its default value from the HTML file.
     */
    public function resetLocalizedText(Request $request)
    {
        $key = $request->get('key');
        
        if (!$key) {
            Toast::error("No key provided");
            return redirect()->route('platform.settings.texts');
        }
        
        try {
            // Convert the key to the expected file prefix format (snake_case to StudlyCase)
            $filePrefix = Str::studly($key);
            
            // Supported languages
            $supportedLanguages = ['de_DE', 'en_US'];
            $count = 0;
            
            // For each language, try to read the original HTML file
            foreach ($supportedLanguages as $language) {
                $filePath = resource_path("language/{$filePrefix}_{$language}.html");
                
                if (File::exists($filePath)) {
                    // Read the content from the original file
                    $content = File::get($filePath);
                    
                    if (!empty($content)) {
                        // Update the database with the original content
                        AppLocalizedText::updateOrCreate(
                            ['content_key' => $key, 'language' => $language],
                            ['content' => $content]
                        );
                        $count++;
                        Log::info("Reset {$key} content for {$language} to default value");
                    } else {
                        Log::warning("Original file {$filePath} is empty");
                    }
                } else {
                    Log::warning("Original file {$filePath} not found. Cannot reset to default.");
                }
            }
            
            if ($count > 0) {
                Toast::success("Localized text '{$key}' was successfully reset to default value");
            } else {
                Toast::error("Could not find default values for '{$key}'");
            }
        } catch (\Exception $e) {
            Log::error("Error resetting content for key {$key}: " . $e->getMessage());
            Toast::error("Error resetting content: " . $e->getMessage());
        }
        
        return;
    }
    
    /**
     * Reset a system text to its default value from the JSON file.
     */
    public function resetSystemText(Request $request)
    {
        $key = $request->get('key');
        
        if (!$key) {
            Toast::error("No key provided");
            return redirect()->route('platform.settings.texts');
        }
        
        try {
            // Supported languages
            $supportedLanguages = ['de_DE', 'en_US'];
            $count = 0;
            
            // For each language, try to read the original JSON file
            foreach ($supportedLanguages as $language) {
                $jsonFile = resource_path("language/{$language}.json");
                
                if (File::exists($jsonFile)) {
                    // Read the content from the JSON file
                    $jsonContent = File::get($jsonFile);
                    $textData = json_decode($jsonContent, true);
                    
                    // Find the key in the JSON data
                    if (isset($textData[$key])) {
                        $content = $textData[$key];
                        
                        if (!empty($content)) {
                            // Update the database with the original content
                            AppSystemText::updateOrCreate(
                                ['content_key' => $key, 'language' => $language],
                                ['content' => $content]
                            );
                            $count++;
                            Log::info("Reset {$key} system text for {$language} to default value");
                        } else {
                            Log::warning("Value for key '{$key}' in {$jsonFile} is empty");
                        }
                    } else {
                        Log::warning("Key '{$key}' not found in {$jsonFile}");
                    }
                } else {
                    Log::warning("JSON file {$jsonFile} not found. Cannot reset system text.");
                }
            }
            
            if ($count > 0) {
                Toast::success("System text '{$key}' was successfully reset to default value");
            } else {
                Toast::error("Could not find default values for '{$key}'");
            }
        } catch (\Exception $e) {
            Log::error("Error resetting system text for key {$key}: " . $e->getMessage());
            Toast::error("Error resetting system text: " . $e->getMessage());
        }
        
        return;
    }

    /**
     * Run the AppLocalizedTextSeeder to populate the database with default values
     */
    public function runLocalizedTextSeeder()
    {
        try {
            \Artisan::call('db:seed', [
                '--class' => 'AppLocalizedTextSeeder'
            ]);
            
            Toast::success('Localized texts were successfully imported from the resource files.');
        } catch (\Exception $e) {
            Log::error('Error running AppLocalizedTextSeeder: ' . $e->getMessage());
            Toast::error('Error importing: ' . $e->getMessage());
        }
        
        // Änderung: Entferne den unnötigen 'language' Parameter
        return;
    }

    /**
     * Run the AppSystemTextSeeder to populate the database with values from JSON files
     */
    public function runSystemTextSeeder()
    {
        try {
            \Artisan::call('db:seed', [
                '--class' => 'AppSystemTextSeeder'
            ]);
            
            Toast::success('System texts were successfully imported from resource files.');
        } catch (\Exception $e) {
            Log::error('Error running AppSystemTextSeeder: ' . $e->getMessage());
            Toast::error('Error importing: ' . $e->getMessage());
        }
        
        return;
    }

    /**
     * Save all text changes (both localized and system texts).
     */
    public function saveChanges(Request $request)
    {
        // Speichern von Localized Texts (HTML-Inhalte)
        $this->saveLocalizedTexts($request);
        
        // Speichern von System Texts (JSON-Inhalte)
        $this->saveSystemTexts($request);
        
        return redirect()->route('platform.settings.texts');
    }

    /**
     * Load all default texts from seeders (both localized and system texts)
     */
    public function loadDefaults()
    {
        // Localized Texts (HTML-Inhalte) aus dem Seeder laden
        $this->runLocalizedTextSeeder();
        
        // System Texts (JSON-Inhalte) aus dem Seeder laden
        $this->runSystemTextSeeder();
        
        Toast::success('All localized and system texts have been successfully imported from resource files.');
        
        return redirect()->route('platform.settings.texts');
    }
}
