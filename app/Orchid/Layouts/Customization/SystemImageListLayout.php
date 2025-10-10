<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Models\AppSystemImage;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class SystemImageListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'systemimages';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [

            TD::make('name', 'Image Name')
                ->sort()
                ->render(function (AppSystemImage $systemImage) {
                    $presenter = $systemImage->presenter();
                    $imagePath = $presenter->image();
                    $title = $presenter->title();
                    $subtitle = $presenter->subTitle();
                    $editUrl = route('platform.customization.systemimages.edit', urlencode($systemImage->name));

                    return '
                        <a href="'.$editUrl.'" class="text-decoration-none">
                            <div class="d-flex align-items-center">
                                <img src="'.$imagePath.'" alt="'.$systemImage->name.'" style="height: 32px; width: 32px; object-fit: contain;" class="rounded me-2">
                                <div>
                                    <div class="fw-medium text-dark">
                                        '.$title.'
                                    </div>
                                    <div class="text-muted small">'.$subtitle.'</div>
                                </div>
                            </div>
                        </a>
                    ';
                }),

            // TD::make('current_image', 'Current Image')
            //    ->render(function (AppSystemImage $systemImage) {
            //        $presenter = $systemImage->presenter();
            //        $imagePath = $presenter->image();
            //
            //        return "<img src=\"{$imagePath}\" alt=\"{$systemImage->name}\" style=\"height: 40px; max-width: 80px; object-fit: contain;\" class=\"rounded\">";
            //    })
            //    ->align(TD::ALIGN_CENTER)
            //    ->width('120px'),

            // TD::make('format', 'Format')
            //    ->render(function (AppSystemImage $systemImage) {
            //        $presenter = $systemImage->presenter();
            //        $format = $presenter->currentFormat();
            //        $statusBadge = $presenter->statusBadge();
            //
            //        return "<span class=\"badge bg-info\">{$format}</span> <span class=\"badge {$statusBadge['class']} ms-1\">{$statusBadge['text']}</span>";
            //    })
            //    ->align(TD::ALIGN_LEFT)
            //    ->width('140px'),

            TD::make('description', 'Description')
                ->render(function (AppSystemImage $systemImage) {
                    $presenter = $systemImage->presenter();
                    $description = $presenter->description();

                    if (empty($description)) {
                        return '<span class="text-secondary fst-italic">No description</span>';
                    }

                    // Limit description length for display
                    $preview = strlen($description) > 80 ? substr($description, 0, 80).'...' : $description;

                    return "<span class=\"text-dark\">{$preview}</span>";
                }),

            TD::make('updated_at', 'Last Updated')
                ->render(function (AppSystemImage $systemImage) {
                    return $systemImage->updated_at?->format('Y-m-d H:i') ?? 'Default';
                })
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            // Actions Column
            TD::make('Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (AppSystemImage $systemImage) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Edit')
                            ->route('platform.customization.systemimages.edit', urlencode($systemImage->name))
                            ->icon('bs.pencil'),

                        Button::make('Reset to Default')
                            ->icon('bs.arrow-clockwise')
                            ->confirm('Are you sure you want to reset this image to default?')
                            ->method('resetSystemImage', ['name' => $systemImage->name]),
                    ])),
        ];
    }
}
