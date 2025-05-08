<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppCss;
use App\Http\Controllers\AppCssController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Screen\Fields\Code;
use Orchid\Support\Facades\Toast;

class StylingSettingsScreen extends Screen
{
    /**
     * CSS entries to display in the CSS Rules tab
     * 
     * @var array
     */
    protected $cssEntries = [
        'custom-styles' => 'Custom CSS',
        /*'style' => 'Main CSS'*/
    ];

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        $data = [];
        
        // Load all configured CSS entries from database
        foreach ($this->cssEntries as $name => $title) {
            $cssEntry = AppCss::where('name', $name)->first();
            $data[$name] = $cssEntry ? $cssEntry->content : '/* No CSS found for ' . $name . ' */';
        }
        
        return $data;
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Styling Settings';
    }
    public function description(): ?string
    {
        return 'Customize the sytling settings to match you organizations corporate identity.';
    }
    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Load Defaults')
                ->icon('arrow-repeat')
                ->method('loadDefaults')
                ->confirm('Are you sure? This will execute the AppCssSeeder and load the default CSS styles.'),

            Button::make('Clear Cache')
                ->icon('trash')
                ->method('clearCssCache')
                ->confirm('This will clear the CSS cache. The cache will be rebuilt on the next page load.'),

            Button::make('Save')
                ->icon('save')
                ->method('saveAllChanges'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        // Build the layouts for each tab
        $systemImagesContent = $this->buildSystemImagesLayout();
        $cssRulesContent = $this->buildCssRulesLayout();
        
        return [
            Layout::tabs([
                'System Images' => $systemImagesContent,
                'CSS Rules' => $cssRulesContent,
            ]),
        ];
    }
    
    /**
     * Build the layout for the System Images tab
     * 
     * @return array
     */
    protected function buildSystemImagesLayout(): array
    {
        $layouts = [];
        
        // Empty layout for now, to be implemented later
        $layouts[] = Layout::rows([
            // Content for System Images will go here
        ]);
        
        return $layouts;
    }
    
    /**
     * Build the layout for the CSS Rules tab
     * 
     * @param array|null $cssEntries Associative array of CSS entries to display [name => title]
     * @return array
     */
    protected function buildCssRulesLayout(array $cssEntries = null): array
    {
        $layouts = [];
        
        // Use provided entries or default to class property
        $entriesToDisplay = $cssEntries ?? $this->cssEntries;
        
        // Create a layout block for each CSS entry
        foreach ($entriesToDisplay as $name => $title) {
            $layouts[] = $this->buildCssEditorBlock($name, $title);
        }
        
        return $layouts;
    }
    
    /**
     * Build a CSS editor block for a specific CSS entry
     *
     * @param string $name CSS entry name
     * @param string $title Display title for the block
     * @return \Orchid\Screen\Layout
     */
    protected function buildCssEditorBlock(string $name, string $title)
    {
        return Layout::block([
            Layout::rows([
                Code::make($name)
                    ->language('css')
                    ->title($title)
                    ->help("These styles are loaded from the database entry with name \"$name\"")
                    ->value($this->query()[$name])
                    ->height('300px'),
            ]),
        ])
        ->title($title)
        ->description("CSS rules for $title")
        ->vertical()
        ->commands([
            Button::make('Reset')
                ->icon('arrow-repeat')
                ->confirm("Are you sure you want to reset the $title CSS to default?")
                ->method('resetCss', ['name' => $name]),

            Button::make('Save')
                ->icon('save')
                ->method('saveCss', ['name' => $name]),
        ]);
    }
    
    /**
     * Save CSS to the database by name
     * 
     * @param Request $request
     * @param string $name CSS entry name to save
     */
    public function saveCss(Request $request, $name)
    {
        $css = $request->get($name);
        
        if ($css !== null) {
            try {
                // Get existing CSS content
                $existingCss = AppCss::where('name', $name)->first();
                
                // Normalize CSS content for comparison
                $normalizedNewCss = $this->normalizeContent($css);
                $normalizedExistingCss = $existingCss ? $this->normalizeContent($existingCss->content) : null;
                
                // Only save if content has actually changed
                if (!$existingCss || $normalizedExistingCss !== $normalizedNewCss) {
                    AppCss::updateOrCreate(
                        ['name' => $name],
                        ['content' => $css]
                    );
                    
                    // Clear the cache for this CSS
                    AppCssController::clearCaches();
                    
                    Toast::info("CSS \"$name\" has been saved");
                    Log::info("CSS \"$name\" updated", [
                        'action' => $existingCss ? 'update' : 'create'
                    ]);
                } else {
                    Toast::info("No changes detected in \"$name\" CSS");
                }
            } catch (\Exception $e) {
                Toast::error("Error saving CSS \"$name\": " . $e->getMessage());
                Log::error("Error saving CSS \"$name\": " . $e->getMessage());
            }
        }
        
        return redirect()->route('platform.settings.styling');
    }
    
    /**
     * Reset CSS to default
     * 
     * @param string $name CSS entry name to reset
     */
    public function resetCss($name)
    {
        try {
            // Get default CSS content from file or use empty default
            $defaultCss = $this->getDefaultCssContent($name);
            
            AppCss::updateOrCreate(
                ['name' => $name],
                ['content' => $defaultCss]
            );
            
            // Clear the cache
            AppCssController::clearCaches();
            
            $title = $this->cssEntries[$name] ?? $name;
            Toast::success("$title has been reset to default");
        } catch (\Exception $e) {
            Toast::error("Error resetting CSS \"$name\": " . $e->getMessage());
            Log::error("Error resetting CSS \"$name\": " . $e->getMessage());
        }
        
        return redirect()->route('platform.settings.styling');
    }
    
    /**
     * Get default CSS content for a specific CSS entry
     *
     * @param string $name CSS entry name
     * @return string Default CSS content
     */
    protected function getDefaultCssContent(string $name): string
    {
        // Check if a default file exists in public/css_defaults directory
        $defaultPath = public_path("css_defaults/{$name}.css");
        
        if (file_exists($defaultPath)) {
            return file_get_contents($defaultPath);
        }
        
        // Return empty CSS with comment if no default file found
        return "/* Default {$name} CSS */";
    }

    /**
     * Save all CSS changes
     *
     * @param Request $request
     */
    public function saveAllChanges(Request $request)
    {
        foreach ($this->cssEntries as $name => $title) {
            $this->saveCss($request, $name);
        }
        
        Toast::success('All CSS settings have been saved');
        return redirect()->route('platform.settings.styling');
    }
    
    /**
     * Clear all CSS caches
     */
    public function clearCssCache()
    {
        try {
            AppCssController::clearCaches();
            Toast::success('CSS cache has been cleared successfully');
        } catch (\Exception $e) {
            Toast::error('Error clearing CSS cache: ' . $e->getMessage());
            Log::error('Error clearing CSS cache: ' . $e->getMessage());
        }
        
        return redirect()->route('platform.settings.styling');
    }
    
    /**
     * Load all default CSS styles from seeders.
     */
    public function loadDefaults()
    {
        try {
            // Run the AppCssSeeder to load defaults
            \Artisan::call('db:seed', [
                '--class' => 'AppCssSeeder'
            ]);
            
            // Clear the cache
            AppCssController::clearCaches();
            
            Toast::success('Default CSS styles have been successfully imported.');
            Log::info('Default CSS styles loaded via seeder');
        } catch (\Exception $e) {
            Log::error('Error running AppCssSeeder: ' . $e->getMessage());
            Toast::error('Error importing default styles: ' . $e->getMessage());
        }
        
        return redirect()->route('platform.settings.styling');
    }
    
    /**
     * These methods are no longer needed since we have the generalized versions above
     * However, we'll keep them for backward compatibility, simply redirecting to the new methods
     */
    public function saveCustomCss(Request $request)
    {
        return $this->saveCss($request, 'custom-styles');
    }
    
    public function resetCustomCss()
    {
        return $this->resetCss('custom-styles');
    }
}
