<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Models\Announcements\Announcement;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class AnnouncementListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'announcements';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('title', 'Title')
                ->sort()
                ->cantHide()
                ->render(function (Announcement $announcement) {
                    return Link::make($announcement->title)
                        ->route('platform.customization.announcements.edit', $announcement);
                }),

            TD::make('type', 'Type')
                ->render(function (Announcement $announcement) {
                    $badgeClass = match($announcement->type) {
                        'policy' => 'bg-danger',
                        'news' => 'bg-info',
                        'system' => 'bg-warning',
                        'event' => 'bg-success',
                        'info' => 'bg-secondary',
                        default => 'bg-secondary',
                    };
                    return "<span class=\"badge {$badgeClass}\">{$announcement->type}</span>";
                })
                ->sort(),

            TD::make('languages', 'Translations')
                ->render(function (Announcement $announcement) {
                    $translations = $announcement->translations;
                    
                    if ($translations->isEmpty()) {
                        return '<span class="text-muted">No translations</span>';
                    }

                    $badges = $translations->map(function ($translation) {
                        $lang = match($translation->locale) {
                            'de_DE' => ['code' => 'DE', 'class' => 'bg-primary'],
                            'en_US' => ['code' => 'EN', 'class' => 'bg-success'],
                            default => ['code' => strtoupper(substr($translation->locale, 0, 2)), 'class' => 'bg-secondary'],
                        };
                        return "<span class=\"badge rounded-pill {$lang['class']} me-1\">{$lang['code']}</span>";
                    })->implode('');

                    return $badges;
                })
                ->align(TD::ALIGN_CENTER)
                ->width('120px'),

            TD::make('is_forced', 'Forced')
                ->render(function (Announcement $announcement) {
                    return $announcement->is_forced
                        ? '<span class="badge bg-warning">Yes</span>'
                        : '<span class="badge bg-secondary">No</span>';
                })
                ->align(TD::ALIGN_CENTER)
                ->width('80px'),

            TD::make('is_global', 'Global')
                ->render(function (Announcement $announcement) {
                    return $announcement->is_global
                        ? '<span class="badge bg-success">Yes</span>'
                        : '<span class="badge bg-secondary">No</span>';
                })
                ->align(TD::ALIGN_CENTER)
                ->width('80px'),

            TD::make('starts_at', 'Starts')
                ->render(function (Announcement $announcement) {
                    return $announcement->starts_at?->format('Y-m-d') ?? '-';
                })
                ->sort(),

            TD::make('expires_at', 'Expires')
                ->render(function (Announcement $announcement) {
                    return $announcement->expires_at?->format('Y-m-d') ?? '-';
                })
                ->sort(),

            TD::make('updated_at', 'Last Updated')
                ->render(function (Announcement $announcement) {
                    return $announcement->updated_at->format('Y-m-d H:i');
                })
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            TD::make('Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (Announcement $announcement) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Edit')
                            ->route('platform.customization.announcements.edit', $announcement)
                            ->icon('bs.pencil'),
                    ])),
        ];
    }
}
