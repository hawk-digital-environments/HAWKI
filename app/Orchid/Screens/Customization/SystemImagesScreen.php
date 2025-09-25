<?php

namespace App\Orchid\Screens\Customization;

use App\Models\AppSystemImage;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Layouts\Customization\SystemImageFiltersLayout;
use App\Orchid\Layouts\Customization\SystemImageListLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Orchid\Attachment\Models\Attachment;
use Orchid\Platform\Dashboard;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class SystemImagesScreen extends Screen
{
    /**
     * System images to manage
     *
     * @var array
     */
    protected $systemImages = [
        'logo_svg' => [
            'title' => 'Logo (SVG)',
            'description' => 'Vector version of the application logo (SVG format)',
            'format' => 'image/*.svg',
            'path' => 'img/logo.svg',
        ],
        'favicon' => [
            'title' => 'Favicon',
            'description' => 'Browser tab icon (ICO or PNG format)',
            'format' => 'image/png,image/x-icon',
            'path' => 'favicon.ico',
        ],
    ];

    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(): iterable
    {
        // Get all defined system images that don't exist in database yet
        $definedImages = array_keys($this->systemImages);
        $existingImages = AppSystemImage::pluck('name')->toArray();
        $missingImages = array_diff($definedImages, $existingImages);

        // Create entries in database for missing defined images (if none exist)
        foreach ($missingImages as $name) {
            $config = $this->systemImages[$name] ?? [];
            AppSystemImage::firstOrCreate(
                ['name' => $name],
                [
                    'description' => $config['description'] ?? 'System image',
                    'file_path' => $config['path'] ?? '',
                    'original_name' => $config['title'] ?? ucfirst(str_replace('_', ' ', $name)),
                    'mime_type' => $config['format'] ?? 'image/*',
                    'active' => false,
                ]
            );
        }

        // Now get all system images with filters
        $systemImages = AppSystemImage::filters()
            ->defaultSort('name')
            ->paginate(50);

        return [
            'systemimages' => $systemImages,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'System Images';
    }

    public function description(): ?string
    {
        return 'Manage system images like logos and favicons to match your organization\'s corporate identity.';
    }

    /**
     * The screen's action buttons.
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Load Default Images')
                ->icon('bs.arrow-clockwise')
                ->method('loadDefaultImages')
                ->confirm('This will reset all images to their defaults. Are you sure?'),

            Button::make('Clear Image Cache')
                ->icon('bs.trash')
                ->method('clearImageCache')
                ->confirm('This will clear all image caches. The cache will be rebuilt on the next page load.'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            CustomizationTabMenu::class,
            SystemImageFiltersLayout::class,
            SystemImageListLayout::class,
        ];
    }

    /**
     * Reset a system image to its default
     */
    public function resetSystemImage(Request $request, $name)
    {
        try {
            $systemImage = AppSystemImage::where('name', $name)->first();

            if ($systemImage) {
                $this->cleanupOldAttachments($name);
                $systemImage->delete();
                Cache::forget("system_image_{$name}");
                Toast::success("Image '{$name}' reset to default successfully!");
            } else {
                Toast::info("Image '{$name}' is already using the default.");
            }
        } catch (\Exception $e) {
            Log::error('Error resetting system image: '.$e->getMessage());
            Toast::error('Error resetting image: '.$e->getMessage());
        }

        return redirect()->route('platform.customization.systemimages');
    }

    /**
     * Load default images for all system images
     */
    public function loadDefaultImages()
    {
        try {
            foreach ($this->systemImages as $name => $config) {
                $systemImage = AppSystemImage::where('name', $name)->first();
                if ($systemImage) {
                    $this->cleanupOldAttachments($name);
                    $systemImage->delete();
                    Cache::forget("system_image_{$name}");
                }
            }

            Toast::success('All images reset to defaults successfully!');
        } catch (\Exception $e) {
            Log::error('Error loading default images: '.$e->getMessage());
            Toast::error('Error loading default images: '.$e->getMessage());
        }

        return redirect()->route('platform.customization.systemimages');
    }

    /**
     * Clear image cache
     */
    public function clearImageCache()
    {
        try {
            foreach ($this->systemImages as $name => $config) {
                Cache::forget("system_image_{$name}");
            }
            Toast::success('Image cache cleared successfully!');
        } catch (\Exception $e) {
            Log::error('Error clearing image cache: '.$e->getMessage());
            Toast::error('Error clearing image cache: '.$e->getMessage());
        }

        return redirect()->route('platform.customization.systemimages');
    }

    /**
     * Clean up old attachments for a system image
     */
    private function cleanupOldAttachments($imageName, $keepAttachmentId = null)
    {
        $systemImage = AppSystemImage::where('name', $imageName)->first();

        if ($systemImage && $systemImage->file_path) {
            $pathPattern = 'storage/img/';
            if (strpos($systemImage->file_path, $pathPattern) === 0) {
                $relativePath = substr($systemImage->file_path, strlen($pathPattern));
                $pathInfo = pathinfo($relativePath);

                $attachments = Dashboard::model(Attachment::class)::where('path', 'img/')
                    ->where('name', $pathInfo['filename'])
                    ->where('extension', $pathInfo['extension'] ?? '');

                if ($keepAttachmentId) {
                    $attachments->where('id', '!=', $keepAttachmentId);
                }

                foreach ($attachments->get() as $attachment) {
                    try {
                        $attachment->delete();
                    } catch (\Exception $e) {
                        Log::warning("Could not delete old attachment: {$e->getMessage()}");
                    }
                }
            }
        }
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
