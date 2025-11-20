<?php

namespace App\Orchid\Screens\Customization;

use App\Models\AppCss;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Services\Frontend\CssCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\Label;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class CssEditScreen extends Screen
{
    use OrchidLoggingTrait;

    /**
     * @var AppCss
     */
    public $css;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query($css_name = null): iterable
    {
        if (is_string($css_name)) {
            $cssName = urldecode($css_name);
            $cssEntry = AppCss::where('name', $cssName)->first();

            return [
                'css' => [
                    'name' => $cssName,
                    'description' => $cssEntry ? $cssEntry->description : 'No description available',
                    'content' => $cssEntry ? $cssEntry->content : '/* CSS content */',
                    'active' => $cssEntry ? $cssEntry->active : true,
                    'has_custom_content' => $cssEntry !== null,
                ],
                'isEdit' => true,
            ];
        }

        return [
            'css' => [
                'name' => '',
                'title' => '',
                'description' => '',
                'content' => '',
                'active' => true,
                'has_custom_content' => false,
            ],
            'isEdit' => false,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        $isEdit = request()->route('css_name') ? true : false;

        return $isEdit
            ? 'Edit CSS Rule: '.urldecode(request()->route('css_name'))
            : 'Create CSS Rule';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Edit CSS rules and customize the application styling';
    }

    /**
     * Permission required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Reset')
                ->icon('bs.arrow-clockwise')
                ->confirm('Are you sure you want to reset this CSS to default?')
                ->method('resetToDefault')
                ->canSee(request()->route('css_name')),

            Button::make('Save')
                ->icon('bs.check-circle')
                ->method('save'),
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
            CustomizationTabMenu::class,

            Layout::block(Layout::rows([
                Label::make('css.name')
                    ->title('CSS Name')
                    ->class('label'),

                Label::make('css.description')
                    ->title('Description'),

                Switcher::make('css.active')
                    ->title('Active')
                    ->help('Inactive CSS rules will not be included in the application')
                    ->sendTrueOrFalse(),
            ]))
                ->title('CSS Information')
                ->description('Basic information about this CSS rule.'),

            Layout::block(Layout::rows([
                Code::make('css.content')
                    ->language('css')
                    ->title('CSS Content')
                    ->help('CSS rules and styles. Use standard CSS syntax.')
                    ->height('400px'),
            ]))
                ->title('CSS Editor')
                ->description('Edit the CSS content for this rule.'),
        ];
    }

    /**
     * Save the CSS rule.
     */
    public function save(Request $request)
    {
        // Get CSS name from route (since it's a label field, not editable)
        $cssName = urldecode(request()->route('css_name'));

        if (! $cssName) {
            Toast::error('CSS name is required.');

            return back();
        }

        $data = $request->get('css');
        $content = $data['content'] ?? '';
        $active = isset($data['active']) ? (bool) $data['active'] : false; // Correct boolean handling for switcher

        $request->validate([
            'css.content' => 'nullable|string',
        ]);

        try {
            $existingCss = AppCss::where('name', $cssName)->first();

            // Prepare data for database
            $cssData = [
                'content' => $content,
                'active' => $active,
            ];

            // Keep existing description if CSS exists, otherwise set default
            if ($existingCss) {
                $cssData['description'] = $existingCss->description;
            } else {
                $cssData['description'] = $this->getDescriptionForCssFile($cssName);
            }

            // Update or create CSS entry
            $css = AppCss::updateOrCreate(
                ['name' => $cssName],
                $cssData
            );

            // @todo avoid using app() helper in favor of dependency injection
            app(CssCache::class)->forgetAll();

            $this->logModelOperation('update', 'css_rule', $cssName, 'success', [
                'content_length' => strlen($content),
                'active' => $active,
            ]);

            Toast::success('CSS rule has been saved and cache cleared successfully.');

            return back();

        } catch (\Exception $e) {
            Log::error('Error saving CSS rule: '.$e->getMessage());
            Toast::error('Error saving CSS rule: '.$e->getMessage());

            return back()->withInput();
        }
    }

    /**
     * Reset CSS to default
     */
    public function resetToDefault(Request $request)
    {
        $cssName = urldecode(request()->route('css_name'));

        try {
            // Load content from CSS file if it exists
            $cssFilePath = public_path("css/{$cssName}.css");
            $defaultContent = '/* Default CSS content */';
            $defaultDescription = "Default CSS stylesheet for {$cssName}";

            if (file_exists($cssFilePath)) {
                $defaultContent = file_get_contents($cssFilePath);

                // Get description from seeder logic
                $defaultDescription = $this->getDescriptionForCssFile($cssName);
            }

            // Update or create with default values from file
            AppCss::updateOrCreate(
                ['name' => $cssName],
                [
                    'content' => $defaultContent,
                    'description' => $defaultDescription,
                    'active' => true,
                ]
            );

            // @todo avoid using app() helper in favor of dependency injection
            app(CssCache::class)->forgetAll();

            $this->logModelOperation('update', 'css_rule', $cssName, 'success', [
                'action' => 'reset_to_default',
                'source' => file_exists($cssFilePath) ? 'file' : 'generated',
            ]);

            Toast::success("CSS rule '{$cssName}' has been reset to default values successfully!");

            // Redirect to avoid session payload issues with large CSS content
            return redirect()->route('platform.customization.css.edit', urlencode($cssName));

        } catch (\Exception $e) {
            Log::error('Error resetting CSS rule: '.$e->getMessage());
            Toast::error('Error resetting CSS rule: '.$e->getMessage());

            // Use redirect instead of back() to avoid session payload issues
            return redirect()->route('platform.customization.css');
        }
    }

    /**
     * Get description for CSS file based on filename
     */
    private function getDescriptionForCssFile(string $filename): string
    {
        $descriptions = [
            'style' => 'Main application stylesheet containing core styling rules and theme definitions.',
            'custom-styles' => 'Custom CSS styles for additional styling and overrides. Use this for organization-specific customizations.',
            'home-style' => 'Styles specific to the home page layout and components.',
            'chat_modules' => 'CSS styles for chat modules and conversation interfaces.',
            'login_style' => 'Styling for login and authentication pages.',
            'settings_style' => 'Styles for settings and configuration pages.',
            'handshake_style' => 'Styles for handshake and connection establishment interfaces.',
            'print_styles' => 'Print-specific CSS styles for proper document formatting.',
            'hljs_custom' => 'Custom syntax highlighting styles for code blocks.',
        ];

        return $descriptions[$filename] ?? "CSS stylesheet for {$filename} related styling.";
    }
}
