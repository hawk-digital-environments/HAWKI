<?php

namespace App\Orchid\Screens\Customization;

use App\Models\AppLocalizedText;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Layouts\Customization\LocalizedTextBasicLayout;
use App\Orchid\Layouts\Customization\LocalizedTextContentLayout;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Services\TextImport\TextImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class LocalizedTextEditScreen extends Screen
{
    use OrchidLoggingTrait;

    /**
     * @var AppLocalizedText
     */
    public $localizedText;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query($content_key = null): iterable
    {
        // If $content_key is a string, find the localized text
        if (is_string($content_key)) {
            $contentKey = urldecode($content_key);

            // Get German and English texts
            $deText = AppLocalizedText::where('content_key', $contentKey)
                ->where('language', 'de_DE')
                ->first();
            $enText = AppLocalizedText::where('content_key', $contentKey)
                ->where('language', 'en_US')
                ->first();

            return [
                'localizedText' => [
                    'content_key' => $contentKey,
                    'description' => $deText ? $deText->description : ($enText ? $enText->description : ''),
                    'de_content' => $deText ? $deText->content : '',
                    'en_content' => $enText ? $enText->content : '',
                ],
                'isEdit' => true,
            ];
        }

        // New localized text
        return [
            'localizedText' => [
                'content_key' => '',
                'description' => '',
                'de_content' => '',
                'en_content' => '',
            ],
            'isEdit' => false,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        $isEdit = request()->route('content_key') ? true : false;

        return $isEdit
            ? 'Edit Localized Text: '.urldecode(request()->route('content_key'))
            : 'Create Localized Text';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        $isEdit = request()->route('content_key') ? true : false;

        return $isEdit
            ? 'Edit localized text content for multiple languages'
            : 'Create new localized text content for multiple languages';
    }

    /**
     * Permission required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        return [

            Button::make('Reset')
                ->icon('bs.arrow-clockwise')
                ->method('resetToDefault')
                ->confirm('This will reset this localized text to default values. Are you sure?'),
            Button::make('Save')
                ->icon('bs.check-circle')
                ->method('save'),
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
            CustomizationTabMenu::class,

            Layout::block(LocalizedTextBasicLayout::class)
                ->title('Basic Information')
                ->description('Content key and description for this localized text.'),

            Layout::block(LocalizedTextContentLayout::class)
                ->title('Content Translation')
                ->description('Text content in different languages for this localized text.'),
        ];
    }

    /**
     * Save the localized text.
     *
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request)
    {
        $data = $request->get('localizedText');

        // Get content_key from form data or route parameter (for edit mode)
        $contentKey = $data['content_key'] ?? urldecode(request()->route('content_key'));

        // Adjust validation rules for edit mode
        $isEdit = request()->route('content_key') !== null;
        $validationRules = [
            'localizedText.de_content' => 'nullable|string',
            'localizedText.en_content' => 'nullable|string',
        ];

        if (! $isEdit) {
            // Only validate content_key and description for create mode
            $validationRules['localizedText.content_key'] = 'required|string|max:255';
            $validationRules['localizedText.description'] = 'nullable|string|max:500';
        }

        $request->validate($validationRules);

        try {
            // Handle description for edit vs create mode
            if ($isEdit) {
                // In edit mode, get description from existing record
                $existingRecord = AppLocalizedText::where('content_key', $contentKey)->first();
                $description = $existingRecord ? $existingRecord->description : '';
            } else {
                // In create mode, use form data or fallback to TextImportService
                $description = $data['description'] ?? '';

                // Get description from TextImportService if empty
                if (empty($description)) {
                    $textImportService = new TextImportService;
                    $reflection = new \ReflectionClass($textImportService);
                    $method = $reflection->getMethod('getLocalizedTextDescription');
                    $method->setAccessible(true);
                    $description = $method->invoke($textImportService, $contentKey);
                }
            }

            // Save German content
            if (! empty($data['de_content'])) {
                AppLocalizedText::updateOrCreate(
                    ['content_key' => $contentKey, 'language' => 'de_DE'],
                    [
                        'content' => $data['de_content'],
                        'description' => $description,
                    ]
                );
            }

            // Save English content
            if (! empty($data['en_content'])) {
                AppLocalizedText::updateOrCreate(
                    ['content_key' => $contentKey, 'language' => 'en_US'],
                    [
                        'content' => $data['en_content'],
                        'description' => $description,
                    ]
                );
            }

            $this->logModelOperation('update', 'localized_text', $contentKey, 'success', [
                'description' => $description,
            ]);

            // Clear caches to ensure changes are immediately visible
            // Clear both LanguageController and LocalizationController caches
            \App\Http\Controllers\LanguageController::clearCaches();
            \App\Http\Controllers\LocalizationController::clearCaches();

            Toast::info('Localized text has been saved successfully.');

            return back();

        } catch (\Exception $e) {
            Log::error('Error saving localized text: '.$e->getMessage());
            Toast::error('Error saving localized text: '.$e->getMessage());

            return back()->withInput();
        }
    }

    /**
     * Reset localized text to defaults
     */
    public function resetToDefault(Request $request)
    {
        $contentKey = urldecode(request()->route('content_key'));

        try {
            // Delete current entries for this content key
            $deletedCount = AppLocalizedText::where('content_key', $contentKey)->delete();

            // Import default values for this specific key
            $textImportService = new TextImportService;
            $importedCount = $textImportService->importSpecificLocalizedText($contentKey);

            $this->logModelOperation('reset', 'localized_text', $contentKey, 'success', [
                'entries_deleted' => $deletedCount,
                'entries_imported' => $importedCount,
            ]);

            // Clear caches to ensure changes are immediately visible
            // Clear both LanguageController and LocalizationController caches
            \App\Http\Controllers\LanguageController::clearCaches();
            \App\Http\Controllers\LocalizationController::clearCaches();

            if ($importedCount > 0) {
                Toast::success("Reset localized text '{$contentKey}' to default values ({$importedCount} entries imported)");
            } else {
                Toast::warning("No default values found for localized text '{$contentKey}' - entries were removed");
            }
        } catch (\Exception $e) {
            Log::error("Error resetting localized text '{$contentKey}': ".$e->getMessage());
            Toast::error('Error resetting localized text: '.$e->getMessage());
        }

        return back();
    }
}
