<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppCss;
use App\Models\AppSystemImage;
use App\Http\Controllers\AppCssController;
use App\Http\Controllers\AppSystemImageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\Upload;
use Orchid\Screen\Fields\Picture;
use Orchid\Support\Facades\Toast;
use Orchid\Attachment\Models\Attachment;

class StylingSettingsScreen extends Screen
{
    /**
     * System images to manage in the System Images tab
     * 
     * @var array
     */
    protected $systemImages = [
        'logo_svg' => [
            'title' => 'Logo (SVG)',
            'description' => 'Vector version of the application logo (SVG format)',
            'format' => 'image/*.svg',
            'path' => 'img/logo.svg'
        ],    
        'favicon' => [
            'title' => 'Favicon',
            'description' => 'Browser tab icon (ICO or PNG format)',
            'format' => 'image/png,image/x-icon',
            'path' => 'favicon.ico'
        ]
    ];
    
    /**
     * CSS entries to display in the CSS Rules tab
     * 
     * @var array
     */
    protected $cssEntries = [
        'custom-styles' => 'Custom CSS',
        'style' => 'Main CSS'
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
        
        // Load system images
        foreach ($this->systemImages as $name => $config) {
            $image = AppSystemImage::getByName($name);
            $data['image_' . $name] = $image ? asset($image->file_path) . '?v=' . time() : asset($config['path']);
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
                ->confirm('This will reset all style settings (CSS and image). Are you sure?'),

            Button::make('Clear Cache')
                ->icon('trash')
                ->method('clearAllCaches')
                ->confirm('This will clear all styling caches (CSS and image caches). The cache will be rebuilt on the next page load.'),

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
        
        // Create an image upload block for each system image
        foreach ($this->systemImages as $name => $config) {
            $layouts[] = Layout::block([
                Layout::rows([
                    // The Picture component already includes upload functionality
                    Picture::make('image_' . $name)
                        //->title($config['title'])
                        ->height(100)
                        ->storage('public')
                        ->targetId() // The stored value will be in the form of id attachment.
                        //->targetUrl() // The saved value will be in the form of a full address before the file.
                        //->targetRelativeUrl() // The saved value will be in the form of a relative address before the file.
                        ->path('img') //Set custom attachment upload path
                        ->acceptedFiles($config['format'])
                        ->maxFileSize(5)
                ])
            ])
            ->title($config['title'])
            ->description($config['description'])
            ->vertical()
            ->commands([
                Button::make('Reset')
                    ->icon('arrow-repeat')
                    ->confirm('Reset to default image?')
                    ->method('resetSystemImage', ['name' => $name]),
                
                Button::make('Save')
                    ->icon('save')
                    ->method('saveReferenceToSystemImage', ['name' => $name])
            ]);
        }
        
        return $layouts;
    }
    
    /**
    * Save a reference to a system image that was uploaded through the Picture component
    * or reset to default if image was removed
    *
    * @param Request $request
    * @param string $name
    */
    public function saveReferenceToSystemImage(Request $request, $name)
    {
        // Get the attachment ID from the Picture field
        $attachmentId = $request->input('image_' . $name);
        
        // Check if the field exists in the request but is empty
        // This indicates the image was intentionally removed in the UI
        if ($request->has('image_' . $name) && empty($attachmentId)) {
            Log::info("Image was removed by user. Resetting to default: {$name}");
            // Reset the image to default
            return $this->resetSystemImage($request, $name);
        }
        
        if (!empty($attachmentId)) {
            try {
                // Find the current system image to get its attachment reference (if any)
                $systemImage = AppSystemImage::where('name', $name)->first();
                
                // Clean up old attachments for this image name to avoid duplicates
                $this->cleanupOldAttachments($name, $attachmentId);
                
                // Find the attachment by ID
                $attachment = Attachment::find($attachmentId);
                
                if (!$attachment) {
                    Toast::error("Image attachment not found in database or no new file was uploaded");
                    return redirect()->route('platform.settings.styling');
                }
                
                // Calculate the full path to the stored file
                $storagePath = 'public/' . $attachment->path . $attachment->name . '.' . $attachment->extension;
                $publicPath = 'storage/' . $attachment->path . $attachment->name . '.' . $attachment->extension;
                
                // Update the system image record to point to this file
                AppSystemImage::updateOrCreate(
                    ['name' => $name],
                    [
                        'file_path' => $publicPath,
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime,
                        'active' => true
                    ]
                );
                
                // Clear the cache
                Cache::forget("system_image_{$name}");
                
                Toast::success("System image '{$this->systemImages[$name]['title']}' has been updated");
                Log::info("System image '{$name}' updated", [
                    'path' => $publicPath,
                    'attachment_id' => $attachmentId
                ]);
                
            } catch (\Exception $e) {
                Log::error("Error saving system image: " . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString()
                ]);
                Toast::error("Error saving image: " . $e->getMessage());
            }
        } else if (!$request->has('image_' . $name)) {
            // This case happens when the method is called but no image field was submitted
            // (which is different from submitting an empty image field)
            Log::warning("No image field was submitted for {$name}");
            Toast::error("No image was selected");
        }
        
        return redirect()->route('platform.settings.styling');
    }
    
    /**
     * Reset system image to default
     *
     * @param Request $request
     * @param string $name
     */
    public function resetSystemImage(Request $request, $name)
    {
        try {
            // Clean up any attachments for this image before resetting
            $this->cleanupOldAttachments($name);
            
            if (AppSystemImageController::resetToDefault($name)) {
                Toast::success("System image '{$this->systemImages[$name]['title']}' has been reset to default");
            } else {
                Toast::error("Default image not found");
            }
        } catch (\Exception $e) {
            Log::error("Error resetting system image: " . $e->getMessage());
            Toast::error("Error resetting image: " . $e->getMessage());
        }
        
        return redirect()->route('platform.settings.styling');
    }
    
    /**
     * Cleanup old attachment records for a system image
     * 
     * @param string $imageName Name of the system image
     * @param string|null $excludeId ID to exclude from deletion (current attachment)
     */
    protected function cleanupOldAttachments(string $imageName, ?string $excludeId = null)
    {
        try {
            // Find system image record
            $systemImage = AppSystemImage::where('name', $imageName)->first();
            
            if (!$systemImage) {
                return;
            }
            
            // Clean up based on two criteria:
            // 1. Attachments matching the original name
            // 2. Attachments with paths that match the system image path pattern
            
            // Find attachments by original name
            if (!empty($systemImage->original_name)) {
                $attachments = Attachment::where('original_name', $systemImage->original_name)
                    ->when($excludeId, function ($query, $excludeId) {
                        return $query->where('id', '!=', $excludeId);
                    })
                    ->get();
                
                foreach ($attachments as $attachment) {
                    $this->deleteAttachment($attachment);
                }
            }
            
            // Find any orphaned attachments that were used for system images
            // by checking path patterns typically used for our system images
            if (empty($excludeId)) {
                $attachments = Attachment::where('groups', 'like', '%system-image%')
                    ->orWhere('groups', 'like', '%system_image%')
                    ->orWhere('path', 'like', '%img/%')
                    ->get();
                    
                foreach ($attachments as $attachment) {
                    if (!empty($excludeId) && $attachment->id == $excludeId) {
                        continue; // Skip the current attachment
                    }
                    
                    // Check if the attachment is still referenced by any system image
                    $stillInUse = AppSystemImage::where('file_path', 'like', '%' . $attachment->name . '%')->exists();
                    
                    if (!$stillInUse) {
                        $this->deleteAttachment($attachment);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Error cleaning up old attachments: " . $e->getMessage());
        }
    }
    
    /**
     * Delete an attachment including its physical file
     *
     * @param Attachment $attachment
     */
    protected function deleteAttachment(Attachment $attachment)
    {
        try {
            // Delete the physical file
            $filePath = storage_path('app/public/' . $attachment->path . $attachment->name . '.' . $attachment->extension);
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
            
            // Delete the database record
            $attachment->delete();
            
            Log::info("Deleted attachment", [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to delete attachment: " . $e->getMessage(), [
                'attachment_id' => $attachment->id
            ]);
        }
    }
    
    /**
     * Load all default system images
     */
    public function loadDefaultImages()
    {
        try {
            // Run the AppSystemImageSeeder to load defaults
            \Artisan::call('db:seed', [
                '--class' => 'AppSystemImageSeeder'
            ]);
            
            // Clear the cache
            AppSystemImageController::clearCaches();
            
            Toast::success('Default system images have been successfully imported.');
            Log::info('Default system images loaded via seeder');
        } catch (\Exception $e) {
            Log::error('Error running AppSystemImageSeeder: ' . $e->getMessage());
            Toast::error('Error importing default images: ' . $e->getMessage());
        }
        
        return redirect()->route('platform.settings.styling');
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
                    Toast::error("No changes detected in \"$name\" CSS");
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
            // Get the default CSS content the same way the AppCssSeeder does
            $defaultCss = $this->getDefaultCssFromSeederSource($name);
            
            if ($defaultCss) {
                AppCss::updateOrCreate(
                    ['name' => $name],
                    ['content' => $defaultCss]
                );
                
                // Clear the cache
                AppCssController::clearCaches();
                
                $title = $this->cssEntries[$name] ?? $name;
                Toast::success("$title has been reset to default");
            } else {
                Toast::error("Could not find default CSS for '$name'");
                Log::error("Could not find default CSS for '$name'");
            }
        } catch (\Exception $e) {
            Toast::error("Error resetting CSS \"$name\": " . $e->getMessage());
            Log::error("Error resetting CSS \"$name\": " . $e->getMessage());
        }
        
        return redirect()->route('platform.settings.styling');
    }

    /**
     * Get default CSS content from same source used by AppCssSeeder
     *
     * @param string $name CSS entry name
     * @return string|null Default CSS content or null if not found
     */
    protected function getDefaultCssFromSeederSource(string $name): ?string
    {
        // The AppCssSeeder uses files from this directory
        $cssDir = public_path('css_v2.0.1_f1');
        $cssPath = $cssDir . '/' . $name . '.css';
        
        if (File::exists($cssPath)) {
            return File::get($cssPath);
        }
        
        // Check if a default file exists in public/css_defaults directory as fallback
        $defaultPath = public_path("css_defaults/{$name}.css");
        
        if (File::exists($defaultPath)) {
            return File::get($defaultPath);
        }
        
        return null;
    }

    /**
     * Get default CSS content for a specific CSS entry
     * This is now a wrapper for getDefaultCssFromSeederSource with fallback
     *
     * @param string $name CSS entry name
     * @return string Default CSS content
     */
    protected function getDefaultCssContent(string $name): string
    {
        $content = $this->getDefaultCssFromSeederSource($name);
        
        // Return empty CSS with comment if no default file found
        if ($content === null) {
            return "/* Default {$name} CSS */";
        }
        
        return $content;
    }

    /**
     * Save all changes (CSS and system images)
     *
     * @param Request $request
     */
    public function saveAllChanges(Request $request)
    {
        $cssCount = 0;
        $imageCount = 0;
        
        // Save all CSS entries
        foreach ($this->cssEntries as $name => $title) {
            $result = $this->saveCss($request, $name);
            if ($result !== false) {
                $cssCount++;
            }
        }
        
        // Save all system images that have pending changes
        foreach ($this->systemImages as $name => $config) {
            $attachmentId = $request->input('image_' . $name);
            if ($attachmentId !== null) {
                $this->saveReferenceToSystemImage($request, $name);
                $imageCount++;
            }
        }
        
        $message = [];
        if ($cssCount > 0) {
            $message[] = 'CSS styles';
        }
        if ($imageCount > 0) {
            $message[] = 'system images';
        }
        
        if (!empty($message)) {
            Toast::success('All ' . implode(' and ', $message) . ' have been saved. Cache has been cleared.');
        } else {
            Toast::info('No changes detected.');
        }
        
        return redirect()->route('platform.settings.styling');
    }
    
    /**
     * Clear all caches (CSS and system images)
     */
    public function clearAllCaches()
    {
        $cssSuccess = false;
        $imageSuccess = false;
        
        try {
            // Clear CSS caches
            AppCssController::clearCaches();
            $cssSuccess = true;
        } catch (\Exception $e) {
            Log::error('Error clearing CSS cache: ' . $e->getMessage());
            Toast::error('Error clearing CSS cache: ' . $e->getMessage());
        }
        
        try {
            // Clear system image caches
            AppSystemImageController::clearCaches();
            $imageSuccess = true;
        } catch (\Exception $e) {
            Log::error('Error clearing system image cache: ' . $e->getMessage());
            Toast::error('Error clearing system image cache: ' . $e->getMessage());
        }
        
        if ($cssSuccess && $imageSuccess) {
            Toast::success('All styling caches have been cleared successfully');
        } else if ($cssSuccess) {
            Toast::success('CSS cache has been cleared successfully');
        } else if ($imageSuccess) {
            Toast::success('System image cache has been cleared successfully');
        }
        
        return redirect()->route('platform.settings.styling');
    }

    /**
     * @deprecated Use clearAllCaches() instead
     */
    public function clearCssCache()
    {
        return $this->clearAllCaches();
    }
    
    /**
     * Load all default settings from seeders (CSS and system images).
     */
    public function loadDefaults()
    {
        $cssSuccess = false;
        $imageSuccess = false;
        
        try {
            // Run the AppCssSeeder to load default CSS styles
            \Artisan::call('db:seed', [
                '--class' => 'AppCssSeeder'
            ]);
            
            // Clear the CSS cache
            AppCssController::clearCaches();
            
            $cssSuccess = true;
            Log::info('Default CSS styles loaded via seeder');
        } catch (\Exception $e) {
            Log::error('Error running AppCssSeeder: ' . $e->getMessage());
            Toast::error('Error importing CSS styles: ' . $e->getMessage());
        }
        
        try {
            // Run the AppSystemImageSeeder to load default images
            \Artisan::call('db:seed', [
                '--class' => 'AppSystemImageSeeder'
            ]);
            
            // Clear the system image cache
            AppSystemImageController::clearCaches();
            
            $imageSuccess = true;
            Log::info('Default system images loaded via seeder');
        } catch (\Exception $e) {
            Log::error('Error running AppSystemImageSeeder: ' . $e->getMessage());
            Toast::error('Error importing system images: ' . $e->getMessage());
        }
        
        // Show appropriate success message based on what was successfully reset
        if ($cssSuccess && $imageSuccess) {
            Toast::success('All default styles and images have been successfully imported.');
        } else if ($cssSuccess) {
            Toast::success('Default CSS styles have been successfully imported.');
        } else if ($imageSuccess) {
            Toast::success('Default system images have been successfully imported.');
        }
        
        return redirect()->route('platform.settings.styling');
    }

    
    /**
     * Normalize content for reliable comparison
     * 
     * @param string $content
     * @return string
     */
     protected function normalizeContent(string $content): string
    {
        // Trim whitespace and normalize line endings
        $normalized = trim($content);
        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);
        
        // Normalize common HTML entities
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5);
        
        return $normalized;
    }
}
