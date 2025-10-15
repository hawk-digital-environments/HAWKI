<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Customization;

use App\Models\Announcements\Announcement;
use App\Orchid\Layouts\Customization\AnnouncementListLayout;
use App\Orchid\Layouts\Customization\CustomizationTabMenu;
use App\Orchid\Traits\OrchidLoggingTrait;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;

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
                ->orderBy('created_at', 'desc')
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
        return 'Manage announcements and guidelines content';
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
                ->route('platform.customization.announcements.create'),
        ];
    }

    /**
     * The screen's layout elements.
     */
    public function layout(): iterable
    {
        return [
            CustomizationTabMenu::class,
            AnnouncementListLayout::class,
        ];
    }
}
