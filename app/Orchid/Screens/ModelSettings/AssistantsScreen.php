<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Models\AiAssistant;
use App\Orchid\Layouts\ModelSettings\AssistantFiltersLayout;
use App\Orchid\Layouts\ModelSettings\AssistantListLayout;
use App\Orchid\Layouts\ModelSettings\AssistantsTabMenu;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class AssistantsScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(): iterable
    {
        return [
            'assistants' => AiAssistant::with(['owner', 'aiModel'])
                ->filters(AssistantFiltersLayout::class)
                ->defaultSort('created_at', 'desc')
                ->paginate(50),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Assistants Management';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'A comprehensive list of all AI assistants, including their configuration and status.';
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
        // Preserve filter parameters when creating new assistant
        $filterParams = request()->only([
            'assistant_search', 
            'assistant_status', 
            'assistant_visibility', 
            'assistant_owner',
            'sort',
            'filter'
        ]);
        
        $createUrl = route('platform.models.assistants.create');
        if (!empty($filterParams)) {
            $createUrl .= '?' . http_build_query($filterParams);
        }
        
        return [
            Link::make(__('Add Assistant'))
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
            
            AssistantFiltersLayout::class,
            AssistantListLayout::class,
        ];
    }

    /**
     * Toggle assistant status.
     */
    public function toggleStatus(Request $request): void
    {
        $assistant = AiAssistant::findOrFail($request->get('id'));
        
        // Cycle through statuses: active -> archived -> draft -> active
        $statusCycle = [
            'active' => 'archived',
            'archived' => 'draft', 
            'draft' => 'active'
        ];
        
        $assistant->status = $statusCycle[$assistant->status] ?? 'active';
        $assistant->save();

        Toast::info('Assistant status updated to: ' . $assistant->status);
    }

    /**
     * Toggle assistant visibility.
     */
    public function toggleVisibility(Request $request): void
    {
        $assistant = AiAssistant::findOrFail($request->get('id'));
        
        // Cycle through visibility: public -> org -> private -> public
        $visibilityCycle = [
            'public' => 'org',
            'org' => 'private',
            'private' => 'public'
        ];
        
        $assistant->visibility = $visibilityCycle[$assistant->visibility] ?? 'public';
        $assistant->save();

        Toast::info('Assistant visibility updated to: ' . $assistant->visibility);
    }

    /**
     * Remove assistant.
     */
    public function remove(Request $request): void
    {
        $assistant = AiAssistant::findOrFail($request->get('id'));
        $assistant->delete();

        Toast::info('Assistant has been deleted.');
    }

}