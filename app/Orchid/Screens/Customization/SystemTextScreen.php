<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Customization;

use App\Models\AppSystemText;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Layouts\Customization\SystemTextFiltersLayout;
use App\Orchid\Layouts\Customization\SystemTextListLayout;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Services\TextImport\TextImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class SystemTextScreen extends Screen
{
    use OrchidLoggingTrait;

    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(): iterable
    {
        $currentLocale = config('app.locale', 'de_DE');

        return [
            'systemtexts' => AppSystemText::query()
                ->where('language', $currentLocale)
                ->filters(SystemTextFiltersLayout::class)
                ->orderBy('content_key')
                ->paginate(50),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'System Text Management';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage system text content and messages used throughout the application.';
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
            Button::make('Export')
                ->icon('bs.upload')
                ->method('exportSystemTexts')
                ->rawClick()
                ->confirm('Export all system texts?'),

            Button::make('Reset All')
                ->icon('bs.arrow-clockwise')
                ->method('resetAllSystemTexts')
                ->confirm('This will reset all system texts to defaults. Are you sure?'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            CustomizationTabMenu::class,

            SystemTextFiltersLayout::class,

            SystemTextListLayout::class,
        ];
    }

    /**
     * Load system text data when opening modal
     */
    public function loadSystemTextOnOpenModal(Request $request): iterable
    {
        $contentKey = $request->get('content_key');

        if (! $contentKey) {
            return [
                'systemText' => [
                    'content_key' => '',
                    'de_content' => '',
                    'en_content' => '',
                ],
            ];
        }

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
        ];
    }

    /**
     * Save system text
     */
    public function saveSystemText(Request $request)
    {
        $data = $request->validate([
            'systemText.content_key' => 'required|string|max:255',
            'systemText.de_content' => 'nullable|string',
            'systemText.en_content' => 'nullable|string',
        ]);

        $contentKey = $data['systemText']['content_key'];
        $deContent = $data['systemText']['de_content'] ?? '';
        $enContent = $data['systemText']['en_content'] ?? '';

        try {
            // Save German text
            if (! empty($deContent)) {
                AppSystemText::updateOrCreate(
                    ['content_key' => $contentKey, 'language' => 'de_DE'],
                    ['content' => $deContent]
                );
            } else {
                AppSystemText::where('content_key', $contentKey)
                    ->where('language', 'de_DE')
                    ->delete();
            }

            // Save English text
            if (! empty($enContent)) {
                AppSystemText::updateOrCreate(
                    ['content_key' => $contentKey, 'language' => 'en_US'],
                    ['content' => $enContent]
                );
            } else {
                AppSystemText::where('content_key', $contentKey)
                    ->where('language', 'en_US')
                    ->delete();
            }

            $this->logBatchOperation('update', 'system_text', [
                'content_key' => $contentKey,
                'languages_updated' => 2,
            ]);

            Toast::success("System text '{$contentKey}' saved successfully");

            // Clear caches to ensure changes are immediately visible
            try {
                \App\Http\Controllers\LanguageController::clearCaches();
                \App\Http\Controllers\LocalizationController::clearCaches();
            } catch (\Exception $e) {
                \Log::error('Error clearing cache after system text save: '.$e->getMessage());
            }

        } catch (\Exception $e) {
            Log::error("Error saving system text '{$contentKey}': ".$e->getMessage());
            Toast::error('Error saving system text: '.$e->getMessage());
        }

        return redirect()->route('platform.customization.systemtexts');
    }

    /**
     * Reset system text to defaults
     */
    public function resetSystemText(Request $request)
    {
        $contentKey = $request->get('content_key');

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

        return redirect()->route('platform.customization.systemtexts');
    }

    /**
     * Delete system text
     */
    public function deleteSystemText(Request $request)
    {
        $contentKey = $request->get('content_key');

        try {
            $deletedCount = AppSystemText::where('content_key', $contentKey)->delete();

            $this->logModelOperation('delete', 'system_text', $contentKey, 'success', [
                'entries_deleted' => $deletedCount,
            ]);

            // Clear caches to ensure changes are immediately visible
            try {
                \App\Http\Controllers\LanguageController::clearCaches();
                \App\Http\Controllers\LocalizationController::clearCaches();
            } catch (\Exception $e) {
                \Log::error('Error clearing cache after system text deletion: '.$e->getMessage());
            }

            Toast::success("Deleted {$deletedCount} entries for system text '{$contentKey}'");
        } catch (\Exception $e) {
            Log::error("Error deleting system text '{$contentKey}': ".$e->getMessage());
            Toast::error('Error deleting system text: '.$e->getMessage());
        }

        return redirect()->route('platform.customization.systemtexts');
    }

    /**
     * Export system texts
     */
    public function exportSystemTexts()
    {
        try {
            $systemTexts = AppSystemText::all()->groupBy('content_key');
            $exportData = [];

            foreach ($systemTexts as $key => $texts) {
                $exportData[$key] = [];
                foreach ($texts as $text) {
                    $exportData[$key][$text->language] = $text->content;
                }
            }

            $fileName = 'system_texts_'.date('Y-m-d_H-i-s').'.json';

            return response()->json($exportData)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
        } catch (\Exception $e) {
            Log::error('Error exporting system texts: '.$e->getMessage());
            Toast::error('Error exporting system texts: '.$e->getMessage());

            return redirect()->route('platform.customization.systemtexts');
        }
    }

    /**
     * Reset all system texts to defaults
     */
    public function resetAllSystemTexts()
    {
        try {
            $currentCount = AppSystemText::count();

            // Clear existing system texts
            AppSystemText::truncate();

            // Import default system texts
            $textImportService = new TextImportService;
            $importedCount = $textImportService->importSystemTexts(true);

            $this->logBatchOperation('reset', 'system_texts', [
                'entries_deleted' => $currentCount,
                'entries_imported' => $importedCount,
            ]);

            // Clear caches to ensure changes are immediately visible
            try {
                \App\Http\Controllers\LanguageController::clearCaches();
                \App\Http\Controllers\LocalizationController::clearCaches();
            } catch (\Exception $e) {
                \Log::error('Error clearing cache after system texts reset: '.$e->getMessage());
            }

            Toast::success("Reset system texts: Removed {$currentCount} entries and imported {$importedCount} default entries");
        } catch (\Exception $e) {
            Log::error('Error resetting system texts: '.$e->getMessage());
            Toast::error('Error resetting system texts: '.$e->getMessage());
        }

        return redirect()->route('platform.customization.systemtexts');
    }
}
