<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\AiAssistant;
use App\Models\AiModel;
use App\Models\AiAssistantPrompt;
use App\Orchid\Layouts\ModelSettings\AssistantsTabMenu;
use App\Orchid\Layouts\ModelSettings\AssistantBasicInfoLayout;
use App\Orchid\Layouts\ModelSettings\AssistantAccessPermissionsLayout;
use App\Orchid\Layouts\ModelSettings\AssistantAiModelOnlyLayout;
use App\Orchid\Layouts\ModelSettings\AssistantDefaultPromptLayout;
use App\Orchid\Layouts\ModelSettings\AssistantToolsLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class AssistantEditScreen extends Screen
{
    /**
     * @var AiAssistant
     */
    public $assistant;

    /**
     * Query data.
     */
    public function query(?AiAssistant $assistant = null): iterable
    {
        $this->assistant = $assistant ?? new AiAssistant();
        
        // Load owner relationship for badge display
        if ($this->assistant->exists) {
            $this->assistant->load('owner');
        }

                // Get available AI models for dropdown
        $aiModels = AiModel::where('is_active', true)
            ->orderBy('label')
            ->get()
            ->pluck('label', 'system_id');

        // Load available prompt types
        $availablePrompts = AiAssistantPrompt::select('title')
            ->distinct()
            ->pluck('title', 'title')
            ->toArray();



        // Prepare creator display name (show "System" for HAWKI)
        $creatorDisplay = '';
        if ($this->assistant->exists && $this->assistant->owner) {
            $creatorDisplay = ($this->assistant->owner->name === 'HAWKI') ? 'System' : $this->assistant->owner->name;
        }

        return [
            'assistant' => $this->assistant,
            'creator_display' => $creatorDisplay,
            'availableModels' => $aiModels,
            'availablePrompts' => $availablePrompts,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return $this->assistant->exists 
            ? 'Edit Assistant: ' . $this->assistant->name
            : 'Create New Assistant';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Configure AI assistant settings, model assignment, and system prompts.';
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        // Preserve filter parameters when navigating back to list
        $filterParams = request()->only([
            'assistant_search', 
            'assistant_status', 
            'assistant_visibility', 
            'assistant_owner',
            'sort',
            'filter'
        ]);
        
        $backUrl = route('platform.models.assistants');
        if (!empty($filterParams)) {
            $backUrl .= '?' . http_build_query($filterParams);
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
     */
    public function layout(): iterable
    {
        return [
            AssistantsTabMenu::class,
            
            Layout::block(AssistantBasicInfoLayout::class)
                ->title('Basic Information')
                ->description('Configure the assistant name, key, description and status.'),

            Layout::block(AssistantAiModelOnlyLayout::class)
                ->title('AI Model')
                ->description('Configure the AI model for this assistant.'),

            Layout::block(AssistantDefaultPromptLayout::class)
                ->title('Default Prompt')
                ->description('Configure the system prompt template for this assistant.'),

            Layout::block(AssistantToolsLayout::class)
                ->title('Tools Configuration')
                ->description('Configure available tools for this assistant (future feature).'),

            Layout::block(AssistantAccessPermissionsLayout::class)
                ->title('Access & Permissions')
                ->description('Configure visibility, ownership and organization access.'),
        ];
    }

    /**
     * Save assistant changes.
     */
    public function save(Request $request)
    {
        try {
            // Store original values for change tracking
            $isNew = !$this->assistant->exists;
            
            // Validation rules
            $rules = [
                'assistant.name' => 'required|string|max:255',
                'assistant.description' => 'nullable|string',
                'assistant.status' => 'required|in:draft,active,archived',
                'assistant.visibility' => 'required|in:private,group,public',
                'assistant.required_role' => 'nullable|exists:roles,slug',
                'assistant.ai_model' => 'nullable|exists:ai_models,system_id',
                'assistant.prompt' => 'nullable|string|max:255',
                'assistant.tools' => 'nullable|array',
            ];

            // For new assistants, require key (owner_id is set automatically)
            if ($isNew) {
                $rules['assistant.key'] = 'required|string|max:255|regex:/^[a-z0-9_]+$/|unique:ai_assistants,key';
            }

            // If visibility is 'group', require a role
            $visibility = $request->input('assistant.visibility');
            if ($visibility === 'group') {
                $rules['assistant.required_role'] = 'required|exists:roles,slug';
            }

            $messages = [
                'assistant.key.regex' => 'The key may only contain lowercase letters, numbers, and underscores.',
                'assistant.key.unique' => 'This key is already taken by another assistant.',
                'assistant.ai_model.exists' => 'The selected AI model is not valid.',
                'assistant.required_role.required' => 'A role is required when visibility is set to "Group".',
                'assistant.required_role.exists' => 'The selected role does not exist.',
            ];

            $request->validate($rules, $messages);

            $originalName = $this->assistant->name ?? null;
            $originalModel = $this->assistant->ai_model ?? null;
            $originalPrompt = $this->assistant->prompt ?? null;

            // Update assistant fields
            $assistantData = $request->get('assistant', []);
            
            // Handle tools as array (if empty, set to null)
            if (isset($assistantData['tools']) && empty($assistantData['tools'])) {
                $assistantData['tools'] = null;
            }

            // Handle required_role based on visibility
            if (isset($assistantData['visibility']) && $assistantData['visibility'] !== 'group') {
                $assistantData['required_role'] = null;
            }

            // For new assistants, set owner to current user (creator)
            if ($isNew) {
                $assistantData['owner_id'] = auth()->id();
            } else {
                // For existing assistants, preserve key and owner_id (read-only fields)
                unset($assistantData['key']);
                unset($assistantData['owner_id']);
            }

            $this->assistant->fill($assistantData);
            $this->assistant->save();

            // Clear AI Config cache when AI model assignments change
            if ($originalModel !== $this->assistant->ai_model || $isNew) {
                \Illuminate\Support\Facades\Cache::flush();
                if (app()->bound(\App\Services\AI\Config\AiConfigService::class)) {
                    app(\App\Services\AI\Config\AiConfigService::class)->clearCache();
                }
                Log::info('AI Config cache cleared due to assistant model change', [
                    'assistant_id' => $this->assistant->id,
                    'old_model' => $originalModel,
                    'new_model' => $this->assistant->ai_model
                ]);
            }

            // Log successful update with change details
            $changes = [];
            if ($isNew) {
                $changes['action'] = 'created';
            } else {
                if ($originalName !== $this->assistant->name) {
                    $changes['name'] = ['from' => $originalName, 'to' => $this->assistant->name];
                }
                if ($originalModel !== $this->assistant->ai_model) {
                    $changes['ai_model'] = ['from' => $originalModel, 'to' => $this->assistant->ai_model];
                }
                if ($originalPrompt !== $this->assistant->prompt) {
                    $changes['prompt'] = ['from' => $originalPrompt, 'to' => $this->assistant->prompt];
                }
            }

            Log::info($isNew ? 'AI Assistant created successfully' : 'AI Assistant updated successfully', [
                'assistant_id' => $this->assistant->id,
                'assistant_name' => $this->assistant->name,
                'assistant_key' => $this->assistant->key,
                'changes' => $changes,
                'updated_by' => auth()->id(),
            ]);

            $message = $isNew 
                ? "Assistant '{$this->assistant->name}' has been created successfully."
                : "Assistant '{$this->assistant->name}' has been updated successfully.";
            
            Toast::success($message);

            // Stay on the edit screen after saving (preserve filter parameters for back button)
            if ($isNew) {
                // For new assistants, redirect to edit screen with ID
                $filterParams = request()->only([
                    'assistant_search', 
                    'assistant_status', 
                    'assistant_visibility', 
                    'assistant_owner',
                    'sort',
                    'filter'
                ]);
                
                $redirectUrl = route('platform.models.assistants.edit', $this->assistant);
                if (!empty($filterParams)) {
                    $redirectUrl .= '?' . http_build_query($filterParams);
                }
                
                return redirect($redirectUrl);
            }
            
            // For existing assistants, stay on current screen
            return back();

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed for assistant ' . ($this->assistant->exists ? 'update' : 'creation'), [
                'assistant_id' => $this->assistant->id ?? null,
                'errors' => $e->errors(),
                'updated_by' => auth()->id(),
            ]);

            throw $e; // Re-throw validation exceptions to show form errors
        } catch (\Exception $e) {
            Log::error('Error ' . ($this->assistant->exists ? 'updating' : 'creating') . ' assistant', [
                'assistant_id' => $this->assistant->id ?? null,
                'assistant_name' => $this->assistant->name ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'updated_by' => auth()->id(),
            ]);

            Toast::error('An error occurred while saving: ' . $e->getMessage());

            return back()->withInput();
        }

        // This line should not be reached due to the redirect in the success case
        // But keeping it as fallback for edit operations
        return redirect()->route('platform.models.assistants.edit', $this->assistant);
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.modelsettings.assistants',
        ];
    }
}