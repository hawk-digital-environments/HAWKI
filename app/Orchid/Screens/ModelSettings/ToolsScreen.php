<?php

declare(strict_types=1);

namespace App\Orchid\Screens\ModelSettings;

use App\Orchid\Layouts\ModelSettings\AssistantsTabMenu;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class ToolsScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(): iterable
    {
        return [];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'AI Tools & Extensions';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage AI tools, extensions, and integrations for enhanced assistant capabilities.';
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
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            AssistantsTabMenu::class,
            
            Layout::view('orchid.tools.placeholder', [
                'title' => 'Tools Management',
                'description' => 'This section is currently under development. Tools management functionality will be available in a future update.',
                'icon' => 'bs.tools',
            ]),
        ];
    }
}