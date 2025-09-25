<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppLocalizedText;
use App\Models\AppSystemText;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class TextsSettingsScreen extends Screen
{
    use OrchidLoggingTrait, OrchidSettingsManagementTrait {
        OrchidLoggingTrait::logBatchOperation insteadof OrchidSettingsManagementTrait;
    }

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Request $request): iterable
    {
        // Load localized texts
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

        // Load system texts
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
            'systemTexts' => $systemTexts,
        ];
    }

    /**
     * The name of the screen displayed in the header.
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
                ->method('loadDefaults')
                ->icon('bs.arrow-repeat'),

            Button::make('Save')
                ->method('saveChanges')
                ->icon('bs.check-circle'),
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
     */
    protected function buildLocalizedTextsLayout(): array
    {
        $layouts = [];

        // Create a block for each localized text
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
                        ->style('min-width: 100%; resize: vertical;'),

                    // English content
                    TextArea::make("texts[{$key}][en_US]")
                        ->title('English Content (en_US)')
                        ->value($enText ? $enText['content'] : '')
                        ->rows(5)
                        ->style('min-width: 100%; resize: vertical;'),
                ]),
            ])
                ->vertical()
                ->title($key)
                ->description('Preview: '.Str::limit(strip_tags($deText ? $deText['content'] : ''), 100))
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

        // Accordion for new texts placed at the bottom
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
                ]),
            ],
        ])->open([]);

        return $layouts;
    }

    /**
     * Build the layout for system texts tab
     */
    protected function buildSystemTextsLayout(): array
    {
        $layouts = [];

        // Search field
        $layouts[] = Layout::rows([
            Input::make('search_system_text')
                ->title('Search')
                ->placeholder('Enter text or key to search')
                ->help('Filter the system texts list')
                ->method('applySearch'),
        ]);

        // Display system texts in blocks
        $systemTexts = $this->query(request())['systemTexts'];

        // Sort alphabetically by key
        ksort($systemTexts);

        foreach ($systemTexts as $key => $texts) {
            // Apply search filter, if present
            $searchTerm = request('search_system_text');
            if (! empty($searchTerm) &&
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
     * Save all changes (both localized and system texts).
     */
    public function saveChanges(Request $request)
    {
        $localizedCreated = 0;
        $localizedUpdated = 0;
        $localizedUnchanged = 0;
        $systemCreated = 0;
        $systemUpdated = 0;
        $systemUnchanged = 0;
        $localizedCreatedKeys = [];
        $localizedUpdatedKeys = [];
        $systemCreatedKeys = [];
        $systemUpdatedKeys = [];

        // Save localized texts
        $texts = $request->get('texts', []);
        if ($texts) {
            foreach ($texts as $key => $languages) {
                foreach ($languages as $language => $content) {
                    if (! empty($content)) {
                        $model = AppLocalizedText::firstOrNew([
                            'content_key' => $key,
                            'language' => $language,
                        ]);

                        $isNew = ! $model->exists;
                        $originalContent = $model->content ?? '';

                        $normalizedNewContent = $this->normalizeContent($content);
                        $normalizedExistingContent = $originalContent ? $this->normalizeContent($originalContent) : '';

                        if ($normalizedNewContent !== $normalizedExistingContent) {
                            $model->content = $content;
                            $model->save();

                            $keyInfo = "{$key} ({$language})";
                            if ($isNew) {
                                $localizedCreated++;
                                $localizedCreatedKeys[] = $keyInfo;
                            } else {
                                $localizedUpdated++;
                                $localizedUpdatedKeys[] = $keyInfo;
                            }
                        } else {
                            $localizedUnchanged++;
                        }
                    }
                }
            }
        }

        // Save system texts
        $systemTexts = $request->get('system_texts', []);
        if ($systemTexts) {
            foreach ($systemTexts as $key => $languages) {
                foreach ($languages as $language => $content) {
                    if (! empty($content)) {
                        $model = AppSystemText::firstOrNew([
                            'content_key' => $key,
                            'language' => $language,
                        ]);

                        $isNew = ! $model->exists;
                        $originalContent = $model->content ?? '';

                        $normalizedNewContent = $this->normalizeContent($content);
                        $normalizedExistingContent = $originalContent ? $this->normalizeContent($originalContent) : '';

                        if ($normalizedNewContent !== $normalizedExistingContent) {
                            $model->content = $content;
                            $model->save();

                            $keyInfo = "{$key} ({$language})";
                            if ($isNew) {
                                $systemCreated++;
                                $systemCreatedKeys[] = $keyInfo;
                            } else {
                                $systemUpdated++;
                                $systemUpdatedKeys[] = $keyInfo;
                            }
                        } else {
                            $systemUnchanged++;
                        }
                    }
                }
            }
        }

        // Log batch operation
        $totalChanges = $localizedCreated + $localizedUpdated + $systemCreated + $systemUpdated;
        if ($totalChanges > 0) {
            $this->logBatchOperation(
                'save_all_texts',
                'all_texts',
                [
                    'localized_created' => $localizedCreated,
                    'localized_updated' => $localizedUpdated,
                    'localized_unchanged' => $localizedUnchanged,
                    'system_created' => $systemCreated,
                    'system_updated' => $systemUpdated,
                    'system_unchanged' => $systemUnchanged,
                    'localized_created_keys' => $localizedCreatedKeys,
                    'localized_updated_keys' => $localizedUpdatedKeys,
                    'system_created_keys' => $systemCreatedKeys,
                    'system_updated_keys' => $systemUpdatedKeys,
                ]
            );
        }

        if ($totalChanges > 0) {
            $localizedTotal = $localizedCreated + $localizedUpdated;
            $systemTotal = $systemCreated + $systemUpdated;
            Toast::success("$totalChanges text entries have been updated ($localizedTotal localized, $systemTotal system)");

            // Clear text caches to ensure changes are immediately visible
            $this->clearTextCache();
        } else {
            Toast::info('No changes detected');
        }

    }

    /**
     * Load all default texts from seeders (both localized and system texts).
     */
    public function loadDefaults()
    {
        try {
            $textImportService = new \App\Services\TextImport\TextImportService;

            // Load localized texts (HTML content) and system texts (JSON content)
            $localizedCount = $textImportService->importLocalizedTexts(true);
            $systemCount = $textImportService->importSystemTexts(true);

            $totalCount = $localizedCount + $systemCount;

            // Log batch operation instead of individual text imports
            $this->logBatchOperation(
                'load_default_texts',
                'default_texts',
                [
                    'localized_texts_imported' => $localizedCount,
                    'system_texts_imported' => $systemCount,
                    'total_texts_imported' => $totalCount,
                ]
            );

            Toast::success("All default texts have been successfully imported from resource files. ($totalCount texts processed: $localizedCount localized, $systemCount system)");

            // Clear text caches to ensure changes are immediately visible
            $this->clearTextCache();

        } catch (\Exception $e) {
            Log::error('Error loading default texts: '.$e->getMessage());
            Toast::error('Error loading defaults: '.$e->getMessage());
        }

    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }
}
