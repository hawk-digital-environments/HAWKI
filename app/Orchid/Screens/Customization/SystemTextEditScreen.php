<?php

namespace App\Orchid\Screens\Customization;

use App\Models\AppSystemText;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Layouts\Customization\SystemTextEditLayout;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Services\TextImport\TextImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SystemTextEditScreen extends Screen
{
    use OrchidLoggingTrait;

    /**
     * @var AppSystemText
     */
    public $systemText;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query($systemText = null): iterable
    {
        // If $systemText is a string (content_key), find the system text
        if (is_string($systemText)) {
            $contentKey = $systemText;

            // Get German and English texts
            $deText = AppSystemText::where('content_key', $contentKey)
                ->where('language', 'de_DE')
                ->first();
            $enText = AppSystemText::where('content_key', $contentKey)
                ->where('language', 'en_US')
                ->first();

            return [
                'systemText' => [
                    'content_key' => $contentKey,
                    'de_content' => $deText ? $deText->content : '',
                    'en_content' => $enText ? $enText->content : '',
                ],
                'isEdit' => true,
            ];
        }

        // New system text
        return [
            'systemText' => [
                'content_key' => '',
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
        $isEdit = request()->route('systemText') ? true : false;

        return $isEdit
            ? 'Edit System Text: '.request()->route('systemText')
            : 'Create System Text';
    }

    /**
     * The description displayed under the screen name.
     */
    public function description(): ?string
    {
        return 'Manage system text translations for the application interface.';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Reset')
                ->icon('bs.arrow-clockwise')
                ->method('resetToDefault')
                ->confirm('This will reset this system text to default values. Are you sure?'),
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

            Layout::block(SystemTextEditLayout::class)
                ->title('System Text Information')
                ->description('System text content key and translations for multiple languages.'),
        ];
    }

    /**
     * Save the system text.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request)
    {
        $data = $request->get('systemText');

        // Get content_key from form data or route parameter (for edit mode)
        $contentKey = $data['content_key'] ?? urldecode(request()->route('systemText'));

        // Adjust validation rules for edit mode
        $isEdit = request()->route('systemText') !== null;
        $validationRules = [
            'systemText.de_content' => 'nullable|string',
            'systemText.en_content' => 'nullable|string',
        ];

        if (! $isEdit) {
            // Only validate content_key for create mode
            $validationRules['systemText.content_key'] = 'required|string|max:255';
        }

        $request->validate($validationRules);

        // Save German content
        if (! empty($data['de_content'])) {
            AppSystemText::updateOrCreate(
                ['content_key' => $contentKey, 'language' => 'de_DE'],
                ['content' => $data['de_content']]
            );
            // Clear cache immediately after saving
            Cache::forget('translations_de_DE');
        }

        // Save English content
        if (! empty($data['en_content'])) {
            AppSystemText::updateOrCreate(
                ['content_key' => $contentKey, 'language' => 'en_US'],
                ['content' => $data['en_content']]
            );
            // Clear cache immediately after saving
            Cache::forget('translations_en_US');
        }

        Toast::info('System text has been saved successfully.');

        // Clear caches to ensure changes are immediately visible
        try {
            // Clear specific translation caches
            Cache::forget('translations_de_DE');
            Cache::forget('translations_en_US');

            // Clear LanguageController request cache
            \App\Http\Controllers\LanguageController::clearCaches();
            \App\Http\Controllers\LocalizationController::clearCaches();
        } catch (\Exception $e) {
            \Log::error('Error clearing cache after system text save: '.$e->getMessage());
        }

        // Stay on the same page instead of redirecting to the list
        return back();
    }

    /**
     * Reset system text to defaults
     */
    public function resetToDefault(Request $request)
    {
        $contentKey = urldecode(request()->route('systemText'));

        try {
            // Delete current entries for this content key
            $deletedCount = AppSystemText::where('content_key', $contentKey)->delete();

            // Import default values for this specific key
            $textImportService = new TextImportService;
            $importedCount = $textImportService->importSpecificSystemText($contentKey);

            $this->logModelOperation('reset', 'system_text', $contentKey, 'success', [
                'entries_deleted' => $deletedCount,
                'entries_imported' => $importedCount,
            ]);

            // Clear caches to ensure changes are immediately visible
            try {
                // Clear specific translation caches
                Cache::forget('translations_de_DE');
                Cache::forget('translations_en_US');

                // Clear LanguageController request cache
                \App\Http\Controllers\LanguageController::clearCaches();
                \App\Http\Controllers\LocalizationController::clearCaches();
            } catch (\Exception $e) {
                \Log::error('Error clearing cache after system text reset: '.$e->getMessage());
            }

            if ($importedCount > 0) {
                Toast::success("Reset system text '{$contentKey}' to default values ({$importedCount} entries imported)");
            } else {
                Toast::warning("No default values found for system text '{$contentKey}' - entries were removed");
            }
        } catch (\Exception $e) {
            Log::error("Error resetting system text '{$contentKey}': ".$e->getMessage());
            Toast::error('Error resetting system text: '.$e->getMessage());
        }

        return back();
    }
}
