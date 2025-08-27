<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\LanguageModel;
use App\Orchid\Layouts\ModelSettings\LanguageModelListLayout;
use App\Orchid\Layouts\ModelSettings\LanguageModelFiltersLayout;
use App\Orchid\Layouts\ModelSettings\LanguageModelTabMenu;
use App\Orchid\Traits\OrchidLoggingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class LanguageModelListScreen extends Screen
{
    use OrchidLoggingTrait;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'models' => LanguageModel::with(['provider', 'provider.apiFormat'])
                ->filters(LanguageModelFiltersLayout::class)
                ->defaultSort('label')
                ->paginate(50)
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Language Models';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage and configure language models from all providers.';
    }

    /**
     * Permission required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.modelsettings.models',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Refresh Models')
                ->icon('bs.arrow-clockwise')
                ->method('refreshModels')
                ->confirm('This will contact all active providers to check for new models. Continue?'),
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
            LanguageModelTabMenu::class,
            LanguageModelFiltersLayout::class,
            LanguageModelListLayout::class,
        ];
    }

    /**
     * Toggle the active status of a model.
     */
    public function toggleActive(Request $request): void
    {
        try {
            $model = LanguageModel::findOrFail($request->get('id'));
            $originalStatus = $model->is_active;
            $model->is_active = !$model->is_active;
            $model->save();

            $status = $model->is_active ? 'activated' : 'deactivated';
            
            Log::info('Language model active status toggled', [
                'model_id' => $model->id,
                'model_label' => $model->label,
                'provider_id' => $model->provider_id,
                'status_change' => ['from' => $originalStatus, 'to' => $model->is_active],
                'updated_by' => auth()->id(),
            ]);

            Toast::success("Model '{$model->label}' has been {$status}.");
            
        } catch (\Exception $e) {
            Log::error('Error toggling model active status', [
                'model_id' => $request->get('id'),
                'error' => $e->getMessage(),
                'updated_by' => auth()->id(),
            ]);
            
            Toast::error('Error updating model status: ' . $e->getMessage());
        }
    }

    /**
     * Toggle the visibility status of a model.
     */
    public function toggleVisible(Request $request): void
    {
        try {
            $model = LanguageModel::findOrFail($request->get('id'));
            $originalVisibility = $model->is_visible;
            $model->is_visible = !$model->is_visible;
            $model->save();

            $status = $model->is_visible ? 'made visible' : 'hidden';
            
            Log::info('Language model visibility toggled', [
                'model_id' => $model->id,
                'model_label' => $model->label,
                'provider_id' => $model->provider_id,
                'visibility_change' => ['from' => $originalVisibility, 'to' => $model->is_visible],
                'updated_by' => auth()->id(),
            ]);

            Toast::success("Model '{$model->label}' has been {$status}.");
            
        } catch (\Exception $e) {
            Log::error('Error toggling model visibility', [
                'model_id' => $request->get('id'),
                'error' => $e->getMessage(),
                'updated_by' => auth()->id(),
            ]);
            
            Toast::error('Error updating model visibility: ' . $e->getMessage());
        }
    }

    /**
     * Delete a model.
     */
    public function deleteModel(Request $request)
    {
        try {
            $model = LanguageModel::findOrFail($request->get('id'));
            $modelName = $model->label;
            $providerId = $model->provider_id;
            
            $model->delete();
            
            Log::info('Language model deleted successfully', [
                'model_id' => $request->get('id'),
                'model_label' => $modelName,
                'provider_id' => $providerId,
                'deleted_by' => auth()->id(),
            ]);
            
            Toast::success("Model '{$modelName}' has been deleted successfully.");
            
        } catch (\Exception $e) {
            Log::error('Error deleting language model', [
                'model_id' => $request->get('id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'deleted_by' => auth()->id(),
            ]);
            
            Toast::error('Error deleting model: ' . $e->getMessage());
        }
        
        return redirect()->back();
    }

    /**
     * Refresh models from all providers.
     */
    public function refreshModels(Request $request)
    {
        // This would redirect to the main model settings screen's refresh function
        // or we could implement it here as well
        Toast::info('Redirecting to model refresh...');
        return redirect()->route('platform.models.language');
    }
}
