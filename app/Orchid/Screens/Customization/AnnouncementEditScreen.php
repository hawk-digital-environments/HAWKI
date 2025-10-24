<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Customization;

use App\Models\Announcements\Announcement;
use App\Models\Announcements\AnnouncementTranslation;
use App\Orchid\Layouts\Customization\AnnouncementBasicLayout;
use App\Orchid\Layouts\Customization\AnnouncementContentLayout;
use App\Orchid\Layouts\Customization\AnnouncementTargetingLayout;
use App\Orchid\Layouts\Customization\AnnouncementTimingLayout;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Traits\OrchidLoggingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class AnnouncementEditScreen extends Screen
{
    use OrchidLoggingTrait;

    public ?Announcement $announcement = null;

    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(Announcement $announcement = null): iterable
    {
        if (!$announcement) {
            $announcement = new Announcement();
        }
        
        $this->announcement = $announcement;

        if ($announcement->exists) {
            // Get translations
            $deTranslation = $announcement->getTranslation('de_DE');
            $enTranslation = $announcement->getTranslation('en_US');

            return [
                'announcement' => $announcement,
                'de_content' => $deTranslation?->content ?? '',
                'en_content' => $enTranslation?->content ?? '',
            ];
        }

        return [
            'announcement' => $announcement,
            'de_content' => '',
            'en_content' => '',
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return $this->announcement->exists
            ? 'Edit Announcement: ' . $this->announcement->title
            : 'Create New Announcement';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage announcement details and translated content (Markdown supported). The identifier is used for internal reference, while the actual title displayed to users comes from the first heading in the markdown content.';
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
                ->method('resetToDefault')
                ->confirm('This will reset the announcement content to the original markdown files. Are you sure?')
                ->canSee($this->announcement->exists),

            Button::make('Save')
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            CustomizationTabMenu::class,

            Layout::rows([
                ...(new AnnouncementBasicLayout)->fields(),
            ])->title('Basic Information'),

            Layout::rows([
                ...(new AnnouncementTargetingLayout)->fields(),
            ])->title('Audience Targeting'),

            Layout::rows([
                ...(new AnnouncementTimingLayout)->fields(),
            ])->title('Timing & Triggers'),

            Layout::rows([
                ...(new AnnouncementContentLayout)->fields(),
            ])->title('Content'),
        ];
    }

    /**
     * Save announcement
     */
    public function save(Request $request, Announcement $announcement)
    {
        // Trim whitespace from content fields before validation (handle null values)
        $deContent = $request->input('de_content');
        $enContent = $request->input('en_content');
        
        $request->merge([
            'de_content' => is_string($deContent) ? trim($deContent) : $deContent,
            'en_content' => is_string($enContent) ? trim($enContent) : $enContent,
        ]);

        $data = $request->validate([
            'announcement.title' => 'required|string|max:255',
            'announcement.view' => 'nullable|string|max:255',
            'announcement.type' => 'required|in:policy,news,system,event,info',
            'announcement.is_forced' => 'boolean',
            'announcement.is_global' => 'boolean',
            'announcement.target_roles' => 'nullable|array',
            'announcement.target_roles.*' => 'string|exists:roles,slug',
            'announcement.anchor' => 'nullable|string|max:255',
            'announcement.starts_at' => 'nullable|date',
            'announcement.expires_at' => 'nullable|date',
            'de_content' => 'required|string|min:3',
            'en_content' => 'required|string|min:3',
        ], [
            'de_content.required' => 'German content is required',
            'de_content.min' => 'German content must be at least 3 characters',
            'en_content.required' => 'English content is required',
            'en_content.min' => 'English content must be at least 3 characters',
        ]);

        try {
            // Auto-generate view key from title if not provided
            if (empty($data['announcement']['view'])) {
                $data['announcement']['view'] = \Illuminate\Support\Str::slug($data['announcement']['title']);
            }

            // If not global, ensure target_roles is set
            if (!$data['announcement']['is_global'] && empty($data['announcement']['target_roles'])) {
                Toast::warning('Please select at least one role for non-global announcements');
                return back()->withInput();
            }

            // If global, clear target_roles
            if ($data['announcement']['is_global']) {
                $data['announcement']['target_roles'] = null;
            }

            // Empty string anchor should be saved as null
            if (empty($data['announcement']['anchor'])) {
                $data['announcement']['anchor'] = null;
            }

            // Save announcement
            $announcement->fill($data['announcement'])->save();

            // Save German translation
            AnnouncementTranslation::updateOrCreate(
                [
                    'announcement_id' => $announcement->id,
                    'locale' => 'de_DE',
                ],
                [
                    'content' => $data['de_content'],
                ]
            );

            // Save English translation
            AnnouncementTranslation::updateOrCreate(
                [
                    'announcement_id' => $announcement->id,
                    'locale' => 'en_US',
                ],
                [
                    'content' => $data['en_content'],
                ]
            );

            $this->logModelOperation(
                $announcement->wasRecentlyCreated ? 'create' : 'update',
                'announcement',
                $announcement->id,
                'success',
                ['title' => $announcement->title]
            );

            Toast::success('Announcement saved successfully');

        } catch (\Exception $e) {
            Log::error('Error saving announcement: ' . $e->getMessage());
            Toast::error('Error saving announcement: ' . $e->getMessage());
        }

        return redirect()->route('platform.customization.announcements');
    }

    /**
     * Reset announcement to default content from markdown files
     */
    public function resetToDefault(Announcement $announcement)
    {
        try {
            $view = $announcement->view;
            $announcementPath = resource_path("announcements/{$view}");

            if (!File::isDirectory($announcementPath)) {
                Toast::warning("No default markdown files found for '{$view}'");
                return redirect()->route('platform.customization.announcements.edit', $announcement);
            }

            $resetCount = 0;

            // Reset German translation
            $deFile = "{$announcementPath}/de_DE.md";
            if (File::exists($deFile)) {
                $content = File::get($deFile);
                AnnouncementTranslation::updateOrCreate(
                    [
                        'announcement_id' => $announcement->id,
                        'locale' => 'de_DE',
                    ],
                    [
                        'content' => $content,
                    ]
                );
                $resetCount++;
            }

            // Reset English translation
            $enFile = "{$announcementPath}/en_US.md";
            if (File::exists($enFile)) {
                $content = File::get($enFile);
                AnnouncementTranslation::updateOrCreate(
                    [
                        'announcement_id' => $announcement->id,
                        'locale' => 'en_US',
                    ],
                    [
                        'content' => $content,
                    ]
                );
                $resetCount++;
            }

            $this->logModelOperation('reset', 'announcement', $announcement->id, 'success', [
                'title' => $announcement->title,
                'translations_reset' => $resetCount,
            ]);

            if ($resetCount > 0) {
                Toast::success("Announcement '{$announcement->title}' reset to default content ({$resetCount} translations)");
            } else {
                Toast::warning("No default markdown files found to reset");
            }

        } catch (\Exception $e) {
            Log::error('Error resetting announcement: ' . $e->getMessage());
            Toast::error('Error resetting announcement: ' . $e->getMessage());
        }

        return redirect()->route('platform.customization.announcements.edit', $announcement);
    }
}
