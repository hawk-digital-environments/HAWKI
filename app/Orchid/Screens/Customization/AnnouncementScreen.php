<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Customization;

use App\Models\Announcements\Announcement;
use App\Orchid\Layouts\Customization\AnnouncementFiltersLayout;
use App\Orchid\Layouts\Customization\AnnouncementListLayout;
use App\Orchid\Traits\OrchidLoggingTrait;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class AnnouncementScreen extends Screen
{
    use OrchidLoggingTrait;

    /**
     * Fetch data to be displayed on the screen.
     */
    public function query(): iterable
    {
        return [
            'announcements' => Announcement::query()
                ->with('translations')
                ->filters(AnnouncementFiltersLayout::class)
                ->defaultSort('created_at', 'desc')
                ->paginate(20),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Announcements';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage announcements and guidelines content. The identifier is used for internal reference only.';
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
            Link::make('Create Announcement')
                ->icon('bs.plus-circle')
                ->route('platform.announcements.create'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            AnnouncementFiltersLayout::class,
            AnnouncementListLayout::class,
        ];
    }

    /**
     * Publish an announcement
     */
    public function publish(Announcement $announcement): void
    {
        // System announcements cannot be unpublished
        if ($announcement->type === 'system') {
            Toast::warning('System announcements cannot be unpublished');
            return;
        }

        $announcement->is_published = true;
        $announcement->save();

        $this->logModelOperation('publish', 'announcement', $announcement->id, 'success', [
            'title' => $announcement->title,
        ]);

        Toast::success("Announcement '{$announcement->title}' has been published");
    }

    /**
     * Unpublish an announcement
     */
    public function unpublish(Announcement $announcement): void
    {
        // System announcements cannot be unpublished
        if ($announcement->type === 'system') {
            Toast::warning('System announcements cannot be unpublished');
            return;
        }

        $announcement->is_published = false;
        $announcement->save();

        $this->logModelOperation('unpublish', 'announcement', $announcement->id, 'success', [
            'title' => $announcement->title,
        ]);

        Toast::success("Announcement '{$announcement->title}' has been unpublished");
    }

    /**
     * Delete an announcement
     */
    public function delete(Announcement $announcement): void
    {
        // System announcements cannot be deleted
        if ($announcement->type === 'system') {
            Toast::error('System announcements cannot be deleted');
            return;
        }

        // Policy announcements cannot be deleted
        if ($announcement->type === 'policy') {
            Toast::error('Policy announcements cannot be deleted');
            return;
        }

        $title = $announcement->title;
        $announcement->delete();

        $this->logModelOperation('delete', 'announcement', $announcement->id, 'success', [
            'title' => $title,
        ]);

        Toast::success("Announcement '{$title}' has been deleted");
    }
}
