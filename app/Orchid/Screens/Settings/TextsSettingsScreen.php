<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppLocalizedText;
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
        // Alle einzigartigen Content-Keys laden
        $contentKeys = AppLocalizedText::select('content_key')
            ->distinct()
            ->orderBy('content_key')
            ->pluck('content_key')
            ->toArray();
        
        // Für jeden Key die Texte in beiden Sprachen sammeln
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
        
        return [
            'localizedTexts' => $localizedTexts
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
                ->method('runLocalizedTextSeeder')
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
        $layouts = [
            Layout::accordion([
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
            ])->open([]),
        ];

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
                    ->method('saveChanges'),
            ]);
        }

        return $layouts;
    }

    /**
     * Save changes to localized texts.
     */
    public function saveChanges(Request $request)
    {
        $texts = $request->get('texts', []);
        $count = 0;
        
        if ($texts) {
            foreach ($texts as $key => $languages) {
                foreach ($languages as $language => $content) {
                    if (!empty($content)) {
                        // Prüfen, ob der Inhalt tatsächlich geändert wurde
                        $existingText = AppLocalizedText::where('content_key', $key)
                            ->where('language', $language)
                            ->first();
                        
                        if (!$existingText || $existingText->content !== $content) {
                            // Nur aktualisieren, wenn es eine Änderung gibt
                            AppLocalizedText::updateOrCreate(
                                [
                                    'content_key' => $key,
                                    'language' => $language
                                ],
                                [
                                    'content' => $content
                                ]
                            );
                            $count++;
                        }
                    }
                }
            }
            
            if ($count > 0) {
                Toast::info("$count localized text entries have been updated");
            } else {
                Toast::info("No changes detected");
            }
        }
        
        return redirect()->route('platform.settings.texts');
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
        
        return redirect()->route('platform.settings.texts');
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
            
            Toast::success('Localized texts were successfully imported from the files.');
        } catch (\Exception $e) {
            Log::error('Error running AppLocalizedTextSeeder: ' . $e->getMessage());
            Toast::error('Error importing: ' . $e->getMessage());
        }
        
        // Änderung: Entferne den unnötigen 'language' Parameter
        return redirect()->route('platform.settings.texts');
    }
}
