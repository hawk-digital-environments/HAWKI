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
        // Start with the base query that includes filters
        $baseQuery = AiAssistantPrompt::filters(PromptFiltersLayout::class);
        
        // Get unique category + title combinations from filtered results
        $promptGroups = $baseQuery->select('category', 'title')
            ->groupBy('category', 'title')
            ->get();

        // Get the representative prompt for each group (minimum ID per group)
        $promptIds = collect($promptGroups)->map(function ($group) {
            return AiAssistantPrompt::where('category', $group->category)
                ->where('title', $group->title)
                ->min('id');
        });

        // Final query with representatives only
        $query = AiAssistantPrompt::whereIn('id', $promptIds)
            ->with(['creator']);
            
        // Apply sorting (either from user request or default)
        $sortField = request()->get('sort', 'category');
        $sortDirection = 'asc';
        
        // Handle Orchid's sort format (e.g., '-title' means title DESC)
        if (str_starts_with($sortField, '-')) {
            $sortField = substr($sortField, 1);
            $sortDirection = 'desc';
        }
        
        // Validate sort field to prevent SQL injection
        $allowedSortFields = ['title', 'category', 'updated_at', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'category';
            $sortDirection = 'asc';
        }
        
        $query->orderBy($sortField, $sortDirection);
        
        // Add secondary sort for consistency
        if ($sortField !== 'title') {
            $query->orderBy('title', 'asc');
        }
        if ($sortField !== 'category') {
            $query->orderBy('category', 'asc');
        }

        return [
            'prompts' => $query->paginate(50),
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