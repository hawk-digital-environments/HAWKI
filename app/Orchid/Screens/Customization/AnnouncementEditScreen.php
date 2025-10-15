<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Customization;

use App\Models\Announcements\Announcement;
use App\Models\Announcements\AnnouncementTranslation;
use App\Orchid\Layouts\Customization\AnnouncementEditLayout;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Traits\OrchidLoggingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
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
        return 'Manage announcement details and translated content (Markdown supported)';
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
            AnnouncementEditLayout::class,
        ];
    }

    /**
     * Save announcement
     */
    public function save(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'announcement.title' => 'required|string|max:255',
            'announcement.view' => 'required|string|max:255',
            'announcement.type' => 'required|in:policy,news,system,event,info',
            'announcement.is_forced' => 'boolean',
            'announcement.is_global' => 'boolean',
            'announcement.anchor' => 'nullable|string|max:255',
            'announcement.starts_at' => 'nullable|date',
            'announcement.expires_at' => 'nullable|date',
            'de_content' => 'nullable|string',
            'en_content' => 'nullable|string',
        ]);

        try {
            // Save announcement
            $announcement->fill($data['announcement'])->save();

            // Save German translation
            if (!empty($data['de_content'])) {
                AnnouncementTranslation::updateOrCreate(
                    [
                        'announcement_id' => $announcement->id,
                        'locale' => 'de_DE',
                    ],
                    [
                        'content' => $data['de_content'],
                    ]
                );
            }

            // Save English translation
            if (!empty($data['en_content'])) {
                AnnouncementTranslation::updateOrCreate(
                    [
                        'announcement_id' => $announcement->id,
                        'locale' => 'en_US',
                    ],
                    [
                        'content' => $data['en_content'],
                    ]
                );
            }

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
