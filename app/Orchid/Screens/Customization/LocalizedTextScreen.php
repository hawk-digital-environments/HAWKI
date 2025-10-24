<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Customization;

use App\Models\AppLocalizedText;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Layouts\Customization\LocalizedTextFiltersLayout;
use App\Orchid\Layouts\Customization\LocalizedTextListLayout;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Services\TextImport\TextImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class LocalizedTextScreen extends Screen
{
    use OrchidLoggingTrait;

    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(): iterable
    {
        $currentLocale = config('app.locale', 'de_DE');

        return [
            'localizedtexts' => AppLocalizedText::query()
                ->where('language', $currentLocale)
                ->filters(LocalizedTextFiltersLayout::class)
                ->orderBy('content_key')
                ->paginate(50),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Localized Text Management';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage localized text content for multiple languages and regions.';
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
            // Button::make('Create Localized Text')
            //    ->icon('bs.plus-circle')
            //    ->route('platform.customization.localizedtexts.create'),

            Button::make('Export')
                ->icon('bs.upload')
                ->method('exportLocalizedTexts')
                ->rawClick()
                ->confirm('Export all localized texts?'),

            Button::make('Reset All')
                ->icon('bs.arrow-clockwise')
                ->method('resetAllLocalizedTexts')
                ->confirm('This will reset all localized texts to defaults. Are you sure?'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            CustomizationTabMenu::class,

            LocalizedTextFiltersLayout::class,

            LocalizedTextListLayout::class,
        ];
    }

    /**
     * Reset localized text to defaults
     */
    public function resetLocalizedText(Request $request)
    {
        $contentKey = $request->get('content_key');

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

        return redirect()->route('platform.customization.localizedtexts');
    }

    /**
     * Delete localized text
     */
    public function deleteLocalizedText(Request $request)
    {
        $contentKey = $request->get('content_key');

        try {
            $deletedCount = AppLocalizedText::where('content_key', $contentKey)->delete();

            $this->logModelOperation('delete', 'localized_text', $contentKey, 'success', [
                'entries_deleted' => $deletedCount,
            ]);

            // Clear caches to ensure changes are immediately visible
            // Clear both LanguageController and LocalizationController caches
            \App\Http\Controllers\LanguageController::clearCaches();
            \App\Http\Controllers\LocalizationController::clearCaches();

            Toast::success("Deleted {$deletedCount} entries for localized text '{$contentKey}'");
        } catch (\Exception $e) {
            Log::error("Error deleting localized text '{$contentKey}': ".$e->getMessage());
            Toast::error('Error deleting localized text: '.$e->getMessage());
        }

        return redirect()->route('platform.customization.localizedtexts');
    }

    /**
     * Export localized texts
     */
    public function exportLocalizedTexts()
    {
        try {
            $localizedTexts = AppLocalizedText::all()->groupBy('content_key');
            $exportData = [];

            foreach ($localizedTexts as $key => $texts) {
                $exportData[$key] = [];
                foreach ($texts as $text) {
                    $exportData[$key][$text->language] = $text->content;
                }
            }

            $fileName = 'localized_texts_'.date('Y-m-d_H-i-s').'.json';

            return response()->json($exportData)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
        } catch (\Exception $e) {
            Log::error('Error exporting localized texts: '.$e->getMessage());
            Toast::error('Error exporting localized texts: '.$e->getMessage());

            return redirect()->route('platform.customization.localizedtexts');
        }
    }

    /**
     * Reset all localized texts to defaults
     */
    public function resetAllLocalizedTexts()
    {
        try {
            $currentCount = AppLocalizedText::count();

            // Clear existing localized texts
            AppLocalizedText::truncate();

            // Import default localized texts
            $textImportService = new TextImportService;
            $importedCount = $textImportService->importLocalizedTexts(true);

            $this->logBatchOperation('reset', 'localized_texts', [
                'entries_deleted' => $currentCount,
                'entries_imported' => $importedCount,
            ]);

            // Clear caches to ensure changes are immediately visible
            // Clear both LanguageController and LocalizationController caches
            \App\Http\Controllers\LanguageController::clearCaches();
            \App\Http\Controllers\LocalizationController::clearCaches();

            Toast::success("Reset localized texts: Removed {$currentCount} entries and imported {$importedCount} default entries");
        } catch (\Exception $e) {
            Log::error('Error resetting localized texts: '.$e->getMessage());
            Toast::error('Error resetting localized texts: '.$e->getMessage());
        }

        return redirect()->route('platform.customization.localizedtexts');
    }
}
