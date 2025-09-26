<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\AiAssistantPrompt;
use App\Orchid\Layouts\ModelSettings\PromptFiltersLayout;
use App\Orchid\Layouts\ModelSettings\PromptListLayout;
use App\Orchid\Layouts\ModelSettings\AssistantsTabMenu;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class PromptsScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(): iterable
    {
        // Get one representative prompt per group (category + title combination)
        $currentLanguage = config('app.locale') === 'en' ? 'en' : 'de';
        
        return [
            'prompts' => AiAssistantPrompt::select('*')
                ->whereIn('id', function($query) {
                    $query->select('id')
                        ->from('ai_assistants_prompts as p1')
                        ->whereRaw('p1.id = (SELECT MIN(p2.id) FROM ai_assistants_prompts p2 WHERE p2.category = p1.category AND p2.title = p1.title)');
                })
                ->with(['creator'])
                ->filters(PromptFiltersLayout::class)
                ->defaultSort('category', 'asc')
                ->defaultSort('title', 'asc')
                ->paginate(50),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Prompts Library';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage AI assistant prompts and templates for consistent AI interactions.';
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
        // Preserve filter parameters when creating new prompt
        $filterParams = request()->only([
            'prompt_search', 
            'prompt_status', 
            'prompt_category',
            'sort',
            'filter'
        ]);
        
        $createUrl = route('platform.models.prompts.create');
        if (!empty($filterParams)) {
            $createUrl .= '?' . http_build_query($filterParams);
        }
        
        return [
            Link::make(__('Add Prompt'))
                ->icon('bs.plus-circle')
                ->href($createUrl),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            AssistantsTabMenu::class,
            
            PromptFiltersLayout::class,
            PromptListLayout::class,
        ];
    }



    /**
     * Remove all prompts in the same group.
     */
    public function remove(Request $request): void
    {
        $prompt = AiAssistantPrompt::findOrFail($request->get('id'));
        
        // Delete all prompts in the same group (same category + title)
        $deletedCount = AiAssistantPrompt::where('category', $prompt->category)
            ->where('title', $prompt->title)
            ->delete();

        Toast::info("Deleted {$deletedCount} prompt(s) from group '{$prompt->title}'");
    }
}