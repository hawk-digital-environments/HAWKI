<?php

namespace App\Orchid\Screens\Customization;

use App\Models\AppSystemImage;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Traits\OrchidLoggingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Orchid\Attachment\Models\Attachment;
use Orchid\Platform\Dashboard;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Picture;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SystemImageEditScreen extends Screen
{
    use OrchidLoggingTrait;

    /**
     * @var AppSystemImage
     */
    public $systemImage;

    /**
     * System images configuration
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
     *
     * @return array
     */
    public function query($image_name = null): iterable
    {
        if (is_string($image_name)) {
            $imageName = urldecode($image_name);
            $systemImage = AppSystemImage::where('name', $imageName)->first();
            $config = $this->systemImages[$imageName] ?? [];

            return [
                'systemImage' => [
                    'name' => $imageName,
                    'title' => $config['title'] ?? ucfirst(str_replace('_', ' ', $imageName)),
                    'description' => $systemImage ? $systemImage->description : ($config['description'] ?? 'Custom system image'),
                    'format' => $config['format'] ?? 'image/*',
                    'current_image' => $systemImage ? asset($systemImage->file_path).'?v='.time() : asset($config['path'] ?? ''),
                    'has_custom_image' => $systemImage !== null,
                ],
                'isEdit' => true,
            ];
        }

        return [
            'systemImage' => [
                'name' => '',
                'title' => '',
                'description' => '',
                'format' => 'image/*',
                'current_image' => '',
                'has_custom_image' => false,
            ],
            'isEdit' => false,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        $isEdit = request()->route('image_name') ? true : false;

        return $isEdit
            ? 'Edit System Image: '.urldecode(request()->route('image_name'))
            : 'Create System Image';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Upload and manage system images like logos and favicons';
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
            Button::make('Save')
                ->icon('bs.check-circle')
                ->method('save'),

            Button::make('Reset to Default')
                ->icon('bs.arrow-clockwise')
                ->confirm('Are you sure you want to reset this image to default?')
                ->method('resetToDefault')
                ->canSee(request()->route('image_name')),
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
                Input::make('systemImage.name')
                    ->title('Image Name')
                    ->placeholder('e.g., logo_svg, favicon')
                    ->help('Unique identifier for this system image')
                    ->required()
                    ->readonly(request()->route('image_name')),

                Input::make('systemImage.title')
                    ->title('Display Title')
                    ->placeholder('Human-readable title for this image')
                    ->help('Title displayed in the admin interface')
                    ->readonly(),

                Input::make('systemImage.description')
                    ->title('Description')
                    ->placeholder('Brief description of this image')
                    ->help('Description of what this image is used for'),

                Input::make('systemImage.format')
                    ->title('Accepted Formats')
                    ->placeholder('image/*')
                    ->help('MIME types accepted for this image')
                    ->readonly(),
            ]))
                ->title('Image Information')
                ->description('Basic information about this system image.'),

            Layout::block(Layout::rows([
                Picture::make('systemImage.image_upload')
                    ->title('Upload New Image')
                    ->height(150)
                    ->storage('public')
                    ->targetId()
                    ->path('img')
                    ->acceptedFiles($this->getAcceptedFiles())
                    ->help('Upload a new image to replace the current one'),
            ]))
                ->title('Image Upload')
                ->description('Upload a new image file for this system image.'),
        ];
    }

    /**
     * Get accepted file formats for the current image
     */
    protected function getAcceptedFiles(): string
    {
        $imageName = request()->route('image_name');
        if ($imageName) {
            $imageName = urldecode($imageName);
            $config = $this->systemImages[$imageName] ?? [];

            return $config['format'] ?? 'image/*';
        }

        return 'image/*';
    }

    /**
     * Save the system image.
     */
    public function save(Request $request)
    {
        $data = $request->get('systemImage');
        $imageName = $data['name'];
        $description = $data['description'] ?? '';
        $attachmentId = $request->input('systemImage.image_upload');

        $request->validate([
            'systemImage.name' => 'required|string|max:255',
            'systemImage.description' => 'nullable|string|max:1000',
        ]);

        try {
            // Always update or create the system image record to save description
            $systemImageData = [
                'description' => $description,
                'active' => true,
            ];

            // If a new image was uploaded, add file data
            if (! empty($attachmentId)) {
                $attachment = Dashboard::model(Attachment::class)::find($attachmentId);

                if (! $attachment) {
                    Toast::error('Image attachment not found.');

                    return back()->withInput();
                }

                // Clean up old attachments
                $this->cleanupOldAttachments($imageName, $attachmentId);

                $publicPath = 'storage/'.$attachment->path.$attachment->name.'.'.$attachment->extension;

                $systemImageData['file_path'] = $publicPath;
                $systemImageData['original_name'] = $attachment->original_name;
                $systemImageData['mime_type'] = $attachment->mime;
            }

            AppSystemImage::updateOrCreate(
                ['name' => $imageName],
                $systemImageData
            );

            Cache::forget("system_image_{$imageName}");

            $logData = ['description' => $description];
            if (! empty($attachmentId)) {
                $logData['file_path'] = $publicPath ?? '';
                $logData['original_name'] = $attachment->original_name ?? '';
            }

            $this->logModelOperation('update', 'system_image', $imageName, 'success', $logData);

            $message = ! empty($attachmentId)
                ? 'System image and description have been saved successfully.'
                : 'System image description has been updated successfully.';

            Toast::info($message);

            return redirect()->route('platform.customization.systemimages');

        } catch (\Exception $e) {
            Log::error('Error saving system image: '.$e->getMessage());
            Toast::error('Error saving system image: '.$e->getMessage());

            return back()->withInput();
        }
    }

    /**
     * Reset image to default
     */
    public function resetToDefault(Request $request)
    {
        $imageName = urldecode(request()->route('image_name'));

        try {
            $systemImage = AppSystemImage::where('name', $imageName)->first();

            if ($systemImage) {
                $this->cleanupOldAttachments($imageName);
                $systemImage->delete();
                Cache::forget("system_image_{$imageName}");

                $this->logModelOperation('delete', 'system_image', $imageName, 'success', [
                    'action' => 'reset_to_default',
                ]);

                Toast::success("Image '{$imageName}' reset to default successfully!");
            } else {
                Toast::info("Image '{$imageName}' is already using the default.");
            }
        } catch (\Exception $e) {
            Log::error('Error resetting system image: '.$e->getMessage());
            Toast::error('Error resetting image: '.$e->getMessage());
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
}
