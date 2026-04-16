<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Models\Announcements\Announcement;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
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
            TD::make('title', 'Identifier')
                ->sort()
                ->cantHide()
                ->render(function (Announcement $announcement) {
                    return Link::make($announcement->title)
                        ->route('platform.announcements.edit', $announcement);
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

            TD::make('is_forced', 'Forced')
                ->render(function (Announcement $announcement) {
                    return $announcement->is_forced
                        ? '<span class="badge bg-warning">Yes</span>'
                        : '<span class="badge bg-secondary">No</span>';
                })
                ->align(TD::ALIGN_CENTER)
                ->width('80px')
                ->sort(),

            TD::make('is_global', 'Audience')
                ->render(function (Announcement $announcement) {
                    if ($announcement->is_global) {
                        return '<span class="badge bg-success">Global</span>';
                    }
                    
                    if (empty($announcement->target_roles)) {
                        return '<span class="badge bg-warning">No roles assigned</span>';
                    }
                    
                    $badges = collect($announcement->target_roles)->map(function ($role) {
                        return "<span class=\"badge bg-primary me-1\">{$role}</span>";
                    })->implode('');
                    
                    return $badges;
                })
                ->sort(),

            TD::make('is_published', 'Status')
                ->render(function (Announcement $announcement) {
                    return $announcement->is_published
                        ? '<span class="badge bg-success">Published</span>'
                        : '<span class="badge bg-secondary">Unpublished</span>';
                })
                ->align(TD::ALIGN_CENTER)
                ->width('120px')
                ->sort(),

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

            TD::make('read_stats', 'Accepted/Read')
                ->render(function (Announcement $announcement) {
                    // Get total read count
                    $readCount = $announcement->users()->wherePivot('seen_at', '!=', null)->count();
                    
                    // If not forced, just show read count
                    if (!$announcement->is_forced) {
                        return $readCount > 0 ? "<span class=\"text-muted\">{$readCount}</span>" : '-';
                    }
                    
                    // For forced announcements, show accepted/read ratio
                    $acceptedCount = $announcement->users()
                        ->wherePivot('accepted_at', '!=', null)
                        ->count();
                    
                    if ($readCount === 0) {
                        return '-';
                    }
                    
                    $percentage = $readCount > 0 ? round(($acceptedCount / $readCount) * 100) : 0;
                    $badgeClass = $percentage >= 80 ? 'bg-success' : ($percentage >= 50 ? 'bg-warning' : 'bg-danger');
                    
                    return "<span class=\"badge {$badgeClass}\">{$acceptedCount}/{$readCount}</span>";
                })
                ->align(TD::ALIGN_CENTER)
                ->width('140px'),

            TD::make('created_at', 'Created')
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            TD::make('Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(function (Announcement $announcement) {
                    $actions = [
                        Link::make('Edit')
                            ->route('platform.announcements.edit', $announcement)
                            ->icon('bs.pencil'),
                    ];

                    // Publish/Unpublish (not for system announcements)
                    if ($announcement->type !== 'system') {
                        if ($announcement->is_published) {
                            $actions[] = Button::make('Unpublish')
                                ->method('unpublish', ['announcement' => $announcement->id])
                                ->icon('bs.eye-slash')
                                ->confirm('Are you sure you want to unpublish this announcement?');
                        } else {
                            $actions[] = Button::make('Publish')
                                ->method('publish', ['announcement' => $announcement->id])
                                ->icon('bs.eye')
                                ->confirm('Are you sure you want to publish this announcement?');
                        }
                    }

                    // Delete (only for news, not for system or policy)
                    if ($announcement->type === 'news' || $announcement->type === 'event' || $announcement->type === 'info') {
                        $actions[] = Button::make('Delete')
                            ->method('delete', ['announcement' => $announcement->id])
                            ->icon('bs.trash')
                            ->confirm('Are you sure you want to delete this announcement? This action cannot be undone.');
                    }

                    return DropDown::make()
                        ->icon('bs.three-dots-vertical')
                        ->list($actions);
                }),
        ];
    }
}
