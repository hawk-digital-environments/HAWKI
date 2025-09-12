<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\LanguageModel;
use App\Models\ProviderSetting;
use App\Orchid\Layouts\ModelSettings\LanguageModelBasicInfoLayout;
use App\Orchid\Layouts\ModelSettings\LanguageModelStatusLayout;
use App\Orchid\Layouts\ModelSettings\LanguageModelSettingsLayout;
use App\Orchid\Layouts\ModelSettings\LanguageModelInformationLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class LanguageModelEditScreen extends Screen
{
    /**
     * @var LanguageModel
     */
    public $model;

    /**
     * Query data.
     *
     * @param LanguageModel $model
     *
     * @return array
     */
    public function query(LanguageModel $model): iterable
    {
        $this->model = $model;
        
        return [
            'model' => $model,
            'settingsJson' => json_encode($model->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
            'informationJson' => json_encode($model->information, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Edit Model: ' . $this->model->label;
    }

    /**
     * Display header description.
     *
     * @return string|null
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
        return [
            Link::make('Cancel')
                ->icon('x-circle')
                ->route('platform.models.language'),

            Button::make('Save')
                ->icon('save')
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
            Layout::block(LanguageModelBasicInfoLayout::class)
                ->title('Model Information')
                ->description('System ID, provider details, and model identification.'),

            Layout::block(LanguageModelStatusLayout::class)
                ->title('Model Status')
                ->description('Control model availability and visibility for users.'),

            Layout::block(LanguageModelSettingsLayout::class)
                ->title('Model Settings')
                ->description('Configure model parameters and behavior in JSON format.'),

            Layout::block(LanguageModelInformationLayout::class)
                ->title('Provider Information')
                ->description('Technical specifications and capabilities from the API provider (read-only).'),
        ];
    }

    /**
     * Save model changes.
     *
     * @param Request $request
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
                'settingsJson' => 'nullable|string',
            ]);

            // Validate and decode settings JSON
            $settingsJson = $request->input('settingsJson');
            $settings = [];
            
            if (!empty($settingsJson)) {
                $settings = json_decode($settingsJson, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('Invalid JSON in model settings', [
                        'model_id' => $this->model->id,
                        'json_error' => json_last_error_msg(),
                        'input' => $settingsJson,
                    ]);
                    
                    Toast::error('Invalid JSON format in Model Settings. Please check your input.');
                    return back()->withInput();
                }
            }

            // Store original values for change tracking
            $originalLabel = $this->model->label;
            $originalActive = $this->model->is_active;
            $originalVisible = $this->model->is_visible;
            $originalSettings = $this->model->settings;

            // Update model fields
            $modelData = $request->get('model', []);
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
            
            Toast::error('An error occurred while saving: ' . $e->getMessage());
            return back()->withInput();
        }

        return redirect()->route('platform.models.language.edit', $this->model);
    }
}
