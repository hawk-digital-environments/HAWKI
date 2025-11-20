<?php

namespace App\Orchid\Screens\Customization;

use App\Models\AppCss;
use App\Orchid\Layouts\Customization\CssFiltersLayout;
use App\Orchid\Layouts\Customization\CssListLayout;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Services\Frontend\CssCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class CssRulesScreen extends Screen
{
    use OrchidLoggingTrait;

    /**
     * Default CSS entries to create if they don't exist
     */
    protected $defaultCssEntries = [
        'custom-styles' => [
            'title' => 'Custom CSS',
            'description' => 'Custom CSS styles for additional styling and overrides. Use this for organization-specific customizations.',
            'content' => '/* Add your custom CSS here */',
        ],
        'style' => [
            'title' => 'Main CSS',
            'description' => 'Main application stylesheet containing core styling rules and theme definitions.',
            'content' => '/* Main application styles */',
        ],
    ];

    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(): iterable
    {
        // Get all defined CSS entries that don't exist in database yet
        $definedCss = array_keys($this->defaultCssEntries);
        $existingCss = AppCss::pluck('name')->toArray();
        $missingCss = array_diff($definedCss, $existingCss);

        // Create entries in database for missing defined CSS entries (if none exist)
        foreach ($missingCss as $name) {
            $config = $this->defaultCssEntries[$name] ?? [];
            AppCss::firstOrCreate(
                ['name' => $name],
                [
                    'description' => $config['description'] ?? 'Custom CSS stylesheet',
                    'content' => $config['content'] ?? '/* CSS content for '.$name.' */',
                    'active' => true,
                ]
            );
        }

        // Now get all CSS entries with filters
        $cssEntries = AppCss::filters()
            ->defaultSort('name')
            ->paginate(50);

        return [
            'css' => $cssEntries,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'CSS Rules';
    }

    public function description(): ?string
    {
        return 'Manage CSS stylesheets to customize the application\'s appearance and design.';
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Export')
                ->icon('bs.upload')
                ->method('exportAllCss')
                ->rawClick()
                ->confirm('Export all CSS files?'),

            Button::make('Reset All')
                ->icon('bs.arrow-clockwise')
                ->method('resetAllCss')
                ->confirm('This will reset all CSS rules to defaults. Are you sure?'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            CustomizationTabMenu::class,
            CssFiltersLayout::class,
            CssListLayout::class,
        ];
    }

    /**
     * Reset CSS to default
     */
    public function resetCss(Request $request)
    {
        $name = $request->get('name');

        try {
            // Load content from CSS file if it exists
            $cssFilePath = public_path("css/{$name}.css");
            $defaultContent = '/* Default CSS content */';
            $defaultDescription = "Default CSS stylesheet for {$name}";

            if (file_exists($cssFilePath)) {
                $defaultContent = file_get_contents($cssFilePath);

                // Get description from seeder logic
                $defaultDescription = $this->getDescriptionForCssFile($name);
            } else {
                // Fallback to config if available
                $config = $this->defaultCssEntries[$name] ?? [];
                $defaultContent = $config['content'] ?? $defaultContent;
                $defaultDescription = $config['description'] ?? $defaultDescription;
            }

            // Update or create with default values from file
            AppCss::updateOrCreate(
                ['name' => $name],
                [
                    'content' => $defaultContent,
                    'description' => $defaultDescription,
                    'active' => true,
                ]
            );

            // @todo avoid using app() helper in favor of dependency injection
            app(CssCache::class)->forgetAll();

            $this->logModelOperation('update', 'css_rule', $name, 'success', [
                'action' => 'reset_to_default',
                'source' => file_exists($cssFilePath) ? 'file' : 'config',
            ]);

            Toast::success("CSS rule '{$name}' has been reset to default values successfully!");

        } catch (\Exception $e) {
            Toast::error("Error resetting CSS rule '{$name}': ".$e->getMessage());
            Log::error("Error resetting CSS rule '{$name}': ".$e->getMessage());
        }

        return redirect()->route('platform.customization.css');
    }

    /**
     * Toggle active status of CSS rule
     */
    public function toggleActive(Request $request)
    {
        $id = $request->get('id');

        try {
            $css = AppCss::findOrFail($id);
            $css->active = ! $css->active;
            $css->save();

            // @todo avoid using app() helper in favor of dependency injection
            app(CssCache::class)->forgetAll();

            $status = $css->active ? 'activated' : 'deactivated';
            $this->logModelOperation('update', 'css_rule', $css->name, 'success', [
                'action' => 'toggle_active',
                'new_status' => $css->active,
            ]);

            Toast::success("CSS rule '{$css->name}' has been {$status}");
        } catch (\Exception $e) {
            Toast::error('Error updating CSS rule: '.$e->getMessage());
            Log::error('Error toggling CSS active status: '.$e->getMessage());
        }

        return redirect()->route('platform.customization.css');
    }

    /**
     * Reset all CSS to defaults
     */
    public function resetAllCss()
    {
        try {
            $resetCount = 0;

            foreach ($this->defaultCssEntries as $name => $config) {
                // Load content from CSS file if it exists
                $cssFilePath = public_path("css/{$name}.css");
                $defaultContent = $config['content'] ?? '/* Default CSS content */';
                $defaultDescription = $config['description'] ?? "Default CSS stylesheet for {$name}";

                if (file_exists($cssFilePath)) {
                    $defaultContent = file_get_contents($cssFilePath);

                    // Get description from seeder logic
                    $defaultDescription = $this->getDescriptionForCssFile($name);
                }

                // Update or create with default values from file
                AppCss::updateOrCreate(
                    ['name' => $name],
                    [
                        'content' => $defaultContent,
                        'description' => $defaultDescription,
                        'active' => true,
                    ]
                );

                $resetCount++;
            }

            if ($resetCount > 0) {
                // @todo avoid using app() helper in favor of dependency injection
                app(CssCache::class)->forgetAll();

                $this->logModelOperation('update', 'css_rules', 'multiple', 'success', [
                    'action' => 'reset_all_to_defaults',
                    'count' => $resetCount,
                ]);

                Toast::success("Reset $resetCount CSS rules to default values successfully!");
            } else {
                Toast::info('No CSS rules found to reset.');
            }
        } catch (\Exception $e) {
            Toast::error('Error resetting CSS: '.$e->getMessage());
            Log::error('Error resetting all CSS: '.$e->getMessage());
        }

        return redirect()->route('platform.customization.css');
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

    /**
     * Export all CSS files
     */
    public function exportAllCss()
    {
        try {
            $cssFiles = AppCss::all();
            $exportData = [];

            foreach ($cssFiles as $css) {
                $exportData[$css->name] = [
                    'name' => $css->name,
                    'description' => $css->description,
                    'content' => $css->content,
                    'active' => $css->active,
                    'created_at' => $css->created_at?->toISOString(),
                    'updated_at' => $css->updated_at?->toISOString(),
                ];
            }

            $fileName = 'css_files_'.date('Y-m-d_H-i-s').'.json';

            $this->logBatchOperation('export', 'css_files', [
                'total_files' => count($exportData),
                'filename' => $fileName,
            ]);

            return response()->json($exportData)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');

        } catch (\Exception $e) {
            Log::error('Error exporting CSS files: '.$e->getMessage());
            Toast::error('Error exporting CSS files: '.$e->getMessage());

            return redirect()->route('platform.customization.css');
        }
    }

    /**
     * Clear CSS cache
     */
    public function clearCssCache()
    {
        try {
            // @todo avoid using app() helper in favor of dependency injection
            app(CssCache::class)->forgetAll();
            Toast::success('CSS cache cleared successfully!');
        } catch (\Exception $e) {
            Toast::error('Error clearing CSS cache: '.$e->getMessage());
            Log::error('Error clearing CSS cache: '.$e->getMessage());
        }

        return redirect()->route('platform.customization.css');
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }
}
