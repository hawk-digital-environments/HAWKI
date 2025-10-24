<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\AiModel;
use App\Orchid\Layouts\ModelSettings\AiModelBasicInfoLayout;
use App\Orchid\Layouts\ModelSettings\AiModelInformationLayout;
use App\Orchid\Layouts\ModelSettings\AiModelStatusLayout;
use App\Orchid\Layouts\ModelSettings\AiModelToolsLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class AiModelEditScreen extends Screen
{
    /**
     * @var AiModel
     */
    public $model;

    /**
     * Query data.
     *
     *
     * @return array
     */
    public function query(AiModel $model): iterable
    {
        $this->model = $model;

        return [
            'model' => $model,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'Edit Model: '.$this->model->label;
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Configure model settings and view information';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        $queryParams = request()->only(['provider_filter', 'active_status', 'visible_status', 'search', 'date_range']);
        $backUrl = route('platform.models.language');
        
        if (!empty($queryParams)) {
            $backUrl .= '?' . http_build_query($queryParams);
        }
        
        return [
            Link::make('Back')
                ->href($backUrl)
                ->icon('bs.arrow-left-circle'),

            Button::make('Save')
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::block(AiModelBasicInfoLayout::class)
                ->title('Model Information')
                ->description('System ID, provider details, and model identification.'),

            Layout::block(AiModelStatusLayout::class)
                ->title('Model Status')
                ->description('Control model availability and visibility for users.'),

            Layout::block(AiModelToolsLayout::class)
                ->title('Model Capabilities (Tools)')
                ->description('Configure which features and tools this model supports.'),

            Layout::block(AiModelInformationLayout::class)
                ->title('Provider Information')
                ->description('Technical specifications and capabilities from the API provider (read-only).'),
        ];
    }

    /**
     * Save model changes.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request)
    {
        try {
            // Validate basic model data
            $request->validate([
                'model.label' => 'required|string|max:255',
                'model.is_active' => 'boolean',
                'model.is_visible' => 'boolean',
                'model.settings.tools.file_upload' => 'nullable|boolean',
                'model.settings.tools.vision' => 'nullable|boolean',
                'model.settings.tools.web_search' => 'nullable|boolean',
            ]);

            // Get current settings and merge with tools from UI
            $settings = $this->model->settings ?? [];
            
            // Merge tools from UI checkboxes into settings
            $modelData = $request->get('model', []);
            if (isset($modelData['settings']['tools'])) {
                $settings['tools'] = $modelData['settings']['tools'];
            }

            // Store original values for change tracking
            $originalLabel = $this->model->label;
            $originalActive = $this->model->is_active;
            $originalVisible = $this->model->is_visible;
            $originalSettings = $this->model->settings;

            // Update model fields (exclude nested settings, we handle it separately)
            unset($modelData['settings']);
            $this->model->fill($modelData);
            $this->model->settings = $settings;
            $this->model->save();

            // Log successful update with change details
            $changes = [];
            if ($originalLabel !== $this->model->label) {
                $changes['label'] = ['from' => $originalLabel, 'to' => $this->model->label];
            }
            if ($originalActive !== $this->model->is_active) {
                $changes['is_active'] = ['from' => $originalActive, 'to' => $this->model->is_active];
            }
            if ($originalVisible !== $this->model->is_visible) {
                $changes['is_visible'] = ['from' => $originalVisible, 'to' => $this->model->is_visible];
            }
            if ($originalSettings !== $this->model->settings) {
                $changes['settings'] = 'updated';
            }

            Log::info('Language model updated successfully', [
                'model_id' => $this->model->id,
                'model_label' => $this->model->label,
                'provider_id' => $this->model->provider_id,
                'changes' => $changes,
                'updated_by' => auth()->id(),
            ]);

            Toast::success("Model '{$this->model->label}' has been updated successfully.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed for language model update', [
                'model_id' => $this->model->id,
                'errors' => $e->errors(),
                'updated_by' => auth()->id(),
            ]);

            throw $e; // Re-throw validation exceptions to show form errors
        } catch (\Exception $e) {
            Log::error('Error updating language model', [
                'model_id' => $this->model->id,
                'model_label' => $this->model->label,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'updated_by' => auth()->id(),
            ]);

            Toast::error('An error occurred while saving: '.$e->getMessage());

            return back()->withInput();
        }

        return redirect()->route('platform.models.language.edit', $this->model);
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.modelsettings.models',
        ];
    }
}
