<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Customization;

use App\Models\Announcements\Announcement;
use App\Models\Announcements\AnnouncementTranslation;
use App\Orchid\Layouts\Customization\AnnouncementBasicLayout;
use App\Orchid\Layouts\Customization\AnnouncementContentLayout;
use App\Orchid\Layouts\Customization\AnnouncementTargetingLayout;
use App\Orchid\Layouts\Customization\AnnouncementTimingLayout;
use App\Orchid\Traits\OrchidLoggingTrait;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class AnnouncementEditScreen extends Screen
{
    use OrchidLoggingTrait;
    use OrchidSettingsManagementTrait {
        OrchidSettingsManagementTrait::logBatchOperation insteadof OrchidLoggingTrait;
    }

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
            Link::make('Back')
                ->icon('bs.arrow-left')
                ->route('platform.announcements'),

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
        // Get content from request or existing translations
        $deContent = $request->input('de_content');
        $enContent = $request->input('en_content');
        
        // Store existing content for comparison
        $existingDeContent = '';
        $existingEnContent = '';
        
        // Track if this is a new record
        $isNewRecord = !$announcement->exists;
        
        // If editing existing announcement, get existing content if not provided in request
        if ($announcement->exists) {
            $deTranslation = $announcement->getTranslation('de_DE');
            $enTranslation = $announcement->getTranslation('en_US');
            
            $existingDeContent = $deTranslation?->content ?? '';
            $existingEnContent = $enTranslation?->content ?? '';
            
            if (is_null($deContent) || $deContent === '') {
                $deContent = $existingDeContent;
            }
            
            if (is_null($enContent) || $enContent === '') {
                $enContent = $existingEnContent;
            }
        }
        
        // Trim whitespace from content fields
        $deContent = is_string($deContent) ? trim($deContent) : '';
        $enContent = is_string($enContent) ? trim($enContent) : '';
        
        // Update request with processed content
        $request->merge([
            'de_content' => $deContent,
            'en_content' => $enContent,
        ]);

        // Check that at least one language has content
        $hasDeContent = !empty($deContent) && strlen($deContent) >= 3;
        $hasEnContent = !empty($enContent) && strlen($enContent) >= 3;
        
        if (!$hasDeContent && !$hasEnContent) {
            Toast::error('At least one language content (German or English) must be provided with minimum 3 characters');
            return back()->withInput();
        }

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
            'de_content' => 'nullable|string',
            'en_content' => 'nullable|string',
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

            // Track if any changes were made
            $hasChanges = false;
            $changedFields = [];

            // Use the trait's saveModelWithChangeDetection for the announcement
            $result = $this->saveModelWithChangeDetection(
                $announcement,
                $data['announcement'],
                $isNewRecord ? 'Announcement created' : 'Announcement updated'
            );

            if ($result['hasChanges']) {
                $hasChanges = true;
                $changedFields = array_merge($changedFields, $result['changedFields']);
            }

            // Save German translation (only if content changed)
            if ($hasDeContent && $deContent !== $existingDeContent) {
                AnnouncementTranslation::updateOrCreate(
                    [
                        'announcement_id' => $announcement->id,
                        'locale' => 'de_DE',
                    ],
                    [
                        'content' => $deContent,
                    ]
                );
                $hasChanges = true;
                $changedFields[] = [
                    'field' => 'translation_de_DE',
                    'old_value' => $existingDeContent ? substr($existingDeContent, 0, 50) . '...' : 'empty',
                    'new_value' => substr($deContent, 0, 50) . '...',
                ];
            }

            // Save English translation (only if content changed)
            if ($hasEnContent && $enContent !== $existingEnContent) {
                AnnouncementTranslation::updateOrCreate(
                    [
                        'announcement_id' => $announcement->id,
                        'locale' => 'en_US',
                    ],
                    [
                        'content' => $enContent,
                    ]
                );
                $hasChanges = true;
                $changedFields[] = [
                    'field' => 'translation_en_US',
                    'old_value' => $existingEnContent ? substr($existingEnContent, 0, 50) . '...' : 'empty',
                    'new_value' => substr($enContent, 0, 50) . '...',
                ];
            }

            // Only show success if changes were actually made
            if ($hasChanges) {
                // Log the complete operation with all changes
                Log::info('Announcement saved with changes', [
                    'announcement_id' => $announcement->id,
                    'announcement_title' => $announcement->title,
                    'operation' => $isNewRecord ? 'create' : 'update',
                    'changed_fields' => $changedFields,
                    'user_id' => auth()->id(),
                ]);

                Toast::success('Announcement saved successfully');
            } else {
                Toast::info('No changes detected');
            }

        } catch (\Exception $e) {
            Log::error('Error saving announcement: ' . $e->getMessage());
            Toast::error('Error saving announcement: ' . $e->getMessage());
        }

        return redirect()->route('platform.announcements');
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
                return redirect()->route('platform.announcements.edit', $announcement);
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

        return redirect()->route('platform.announcements.edit', $announcement);
    }
}
