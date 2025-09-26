<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\AiAssistant;
use App\Models\AiModel;
use App\Models\AiAssistantPrompt;
use App\Orchid\Layouts\ModelSettings\AssistantEditLayout;
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

                // Get available AI models for dropdown
        $aiModels = AiModel::where('is_active', true)
            ->orderBy('label')
            ->get()
            ->pluck('label', 'system_id');

        // Load available prompt types
        $availablePrompts = AiAssistantPrompt::select('prompt_type')
            ->distinct()
            ->pluck('prompt_type', 'prompt_type')
            ->toArray();

        // Get current prompt text for display (German by default)
        $currentPromptText = '';
        if ($this->assistant->prompt) {
            $currentPromptText = AiAssistantPrompt::getPrompt($this->assistant->prompt, 'de_DE') 
                ?? AiAssistantPrompt::getPrompt($this->assistant->prompt, 'en_US')
                ?? '';
        }

        return [
            'assistant' => $this->assistant,
            'availableModels' => $availableModels,
            'availablePrompts' => $availablePrompts,
            'currentPromptText' => $currentPromptText,
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
        $queryParams = request()->only(['assistant_search', 'assistant_status', 'assistant_visibility', 'assistant_owner']);
        $backUrl = route('platform.models.assistants');
        
        if (!empty($queryParams)) {
            $backUrl .= '?' . http_build_query($queryParams);
        }
        
        return [
            Link::make('Back to Assistants')
                ->href($backUrl)
                ->icon('bs.arrow-left-circle'),

            Button::make('Save Assistant')
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
            Layout::block(AssistantEditLayout::class)
                ->title('Assistant Configuration')
                ->description('Configure the AI assistant settings, model assignment, and system prompts.'),
        ];
    }

    /**
     * Save assistant changes.
     */
    public function save(Request $request)
    {
        try {
            // Validation rules
            $rules = [
                'assistant.name' => 'required|string|max:255',
                'assistant.key' => [
                    'required',
                    'string',
                    'max:255',
                    'regex:/^[a-z0-9_]+$/',
                    $this->assistant->exists 
                        ? 'unique:ai_assistants,key,' . $this->assistant->id
                        : 'unique:ai_assistants,key'
                ],
                'assistant.description' => 'nullable|string',
                'assistant.status' => 'required|in:draft,active,archived',
                'assistant.visibility' => 'required|in:private,org,public',
                'assistant.org_id' => 'nullable|uuid',
                'assistant.owner_id' => 'required|exists:users,id',
                'assistant.ai_model' => 'nullable|exists:ai_models,system_id',
                'assistant.prompt' => 'nullable|string|max:255',
                'assistant.tools' => 'nullable|array',
            ];

            $messages = [
                'assistant.key.regex' => 'The key may only contain lowercase letters, numbers, and underscores.',
                'assistant.key.unique' => 'This key is already taken by another assistant.',
                'assistant.ai_model.exists' => 'The selected AI model is not valid.',
            ];

            $request->validate($rules, $messages);

            // Store original values for change tracking
            $isNew = !$this->assistant->exists;
            $originalName = $this->assistant->name ?? null;
            $originalModel = $this->assistant->ai_model ?? null;
            $originalPrompt = $this->assistant->prompt ?? null;

            // Update assistant fields
            $assistantData = $request->get('assistant', []);
            
            // Handle tools as array (if empty, set to null)
            if (isset($assistantData['tools']) && empty($assistantData['tools'])) {
                $assistantData['tools'] = null;
            }

            $this->assistant->fill($assistantData);
            $this->assistant->save();

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