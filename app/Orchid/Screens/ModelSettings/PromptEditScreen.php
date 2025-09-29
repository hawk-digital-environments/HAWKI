<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\AiAssistantPrompt;
use App\Orchid\Layouts\ModelSettings\PromptBasicInfoLayout;
use App\Orchid\Layouts\ModelSettings\PromptMultiLanguageLayout;
use App\Orchid\Layouts\ModelSettings\PromptMetaLayout;
use App\Orchid\Layouts\ModelSettings\PromptEnglishLayout;
use App\Orchid\Layouts\ModelSettings\AssistantsTabMenu;
use Database\Seeders\AiAssistantPromptSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class PromptEditScreen extends Screen
{
    /**
     * @var AiAssistantPrompt|null
     */
    public $prompt;

    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(?AiAssistantPrompt $prompt = null): iterable
    {
        return [
            'prompt' => $prompt,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return $this->prompt && $this->prompt->exists 
            ? 'Edit Prompt: ' . $this->prompt->title
            : 'Create New Prompt';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return $this->prompt && $this->prompt->exists
            ? 'Modify the prompt details and content.'
            : 'Create a new AI assistant prompt template.';
    }

    /**
     * Permission required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.modelsettings.assistants',
        ];
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        // Preserve filter parameters for back navigation
        $filterParams = request()->only([
            'prompt_search', 
            'prompt_status', 
            'prompt_category',
            'sort',
            'filter'
        ]);
        
        $backUrl = route('platform.models.prompts');
        if (!empty($filterParams)) {
            $backUrl .= '?' . http_build_query($filterParams);
        }

        $buttons = [
            Link::make(__('Back'))
                ->icon('bs.arrow-left')
                ->href($backUrl),
        ];

        // Add reset button for existing prompts
        if ($this->prompt && $this->prompt->exists) {
            $buttons[] = Button::make(__('Reset'))
                ->icon('bs.arrow-clockwise')
                ->confirm(__('This will reset this prompt to default values. Are you sure?'))
                ->method('resetToDefault');
        }

        $buttons[] = Button::make(__('Save'))
            ->icon('bs.check-circle')
            ->method('save');

        return $buttons;
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            AssistantsTabMenu::class,
            
            Layout::block(PromptMetaLayout::class)
                ->title('Basic Info')
                ->description('Basic information about this prompt template.'),
            
            Layout::block(PromptMultiLanguageLayout::class)
                ->title('German Content')
                ->description('German version of the prompt text.'),
                
            Layout::block(PromptEnglishLayout::class)
                ->title('English Content')
                ->description('English version of the prompt text.'),
        ];
    }

    /**
     * Save the prompt.
     */
    public function save(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'prompt.category' => 'required|string|max:100',
            'prompt_title' => 'required|string|max:255',
            'prompt_description' => 'nullable|string|max:1000',
            'content_de' => 'required|string|min:10',
            'content_en' => 'required|string|min:10',
        ]);

        $category = $validated['prompt']['category'];
        $title = $validated['prompt_title'];
        $description = $validated['prompt_description'];
        $contentDe = $validated['content_de'];
        $contentEn = $validated['content_en'];
        
        try {
            // Prepare base data for both versions
            $baseData = [
                'category' => $category,
                'description' => $description,
            ];

            // Save or update German version
            $germanPrompt = AiAssistantPrompt::where('title', $title)
                ->where('language', 'de_DE')
                ->first();
                
            if ($germanPrompt) {
                // Update existing prompt (preserve creator)
                $germanPrompt->update(array_merge($baseData, [
                    'content' => $contentDe,
                ]));
            } else {
                // Create new prompt (set creator)
                $germanPrompt = AiAssistantPrompt::create(array_merge($baseData, [
                    'title' => $title,
                    'language' => 'de_DE',
                    'content' => $contentDe,
                    'created_by' => Auth::id(),
                ]));
            }

            // Save or update English version
            $englishPrompt = AiAssistantPrompt::where('title', $title)
                ->where('language', 'en_US')
                ->first();
                
            if ($englishPrompt) {
                // Update existing prompt (preserve creator)
                $englishPrompt->update(array_merge($baseData, [
                    'content' => $contentEn,
                ]));
            } else {
                // Create new prompt (set creator)
                $englishPrompt = AiAssistantPrompt::create(array_merge($baseData, [
                    'title' => $title,
                    'language' => 'en_US',
                    'content' => $contentEn,
                    'created_by' => Auth::id(),
                ]));
            }

            // Clear language controller caches to update translation arrays
            \App\Http\Controllers\LanguageController::clearPromptCaches();
            
            Toast::success(__('Prompt saved successfully in both languages.'));
            
        } catch (\Exception $e) {
            Toast::error(__('Error saving prompt: ') . $e->getMessage());
            return back()->withInput();
        }

        // Preserve filter parameters for redirect
        $filterParams = request()->only([
            'prompt_search', 
            'prompt_status', 
            'prompt_category',
            'sort',
            'filter'
        ]);
        
        $redirectUrl = route('platform.models.prompts');
        if (!empty($filterParams)) {
            $redirectUrl .= '?' . http_build_query($filterParams);
        }

        Toast::info(__('Prompt saved successfully.'));

        return redirect($redirectUrl);
    }

    /**
     * Reset the prompt to default values.
     */
    public function resetToDefault(Request $request): \Illuminate\Http\RedirectResponse
    {
        if ($this->prompt && $this->prompt->exists) {
            $title = $this->prompt->title;
            
            try {
                // Delete current entries for this prompt title (both languages)
                $deletedCount = AiAssistantPrompt::where('title', $title)->delete();
                
                // Re-run the seeder to restore default prompts
                $seeder = new AiAssistantPromptSeeder();
                $seeder->run();
                
                // Check if the prompt was restored
                $restoredCount = AiAssistantPrompt::where('title', $title)->count();
                
                if ($restoredCount > 0) {
                    Toast::success(__("Reset prompt '{title}' to default values ({count} entries restored)", [
                        'title' => $title,
                        'count' => $restoredCount
                    ]));
                } else {
                    Toast::warning(__("No default values found for prompt '{title}' - entries were removed", [
                        'title' => $title
                    ]));
                }
                
                // Clear language controller caches after reset
                \App\Http\Controllers\LanguageController::clearPromptCaches();
                
            } catch (\Exception $e) {
                Toast::error(__('Error resetting prompt: ') . $e->getMessage());
                return back();
            }
        }

        // Preserve filter parameters for redirect
        $filterParams = request()->only([
            'prompt_search', 
            'prompt_status', 
            'prompt_category',
            'sort',
            'filter'
        ]);
        
        $redirectUrl = route('platform.models.prompts');
        if (!empty($filterParams)) {
            $redirectUrl .= '?' . http_build_query($filterParams);
        }

        return redirect($redirectUrl);
    }
}