<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Models\AppCss;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class CssListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'css';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('name', 'CSS Rule Name')
                ->sort()
                ->render(function (AppCss $css) {
                    return Link::make($css->name)
                        ->route('platform.customization.css.edit', urlencode($css->name));
                }),

            TD::make('description', 'Description')
                ->render(function (AppCss $css) {
                    $description = $css->description;

                    if (empty($description)) {
                        return '<span class="text-secondary fst-italic">No description</span>';
                    }

                    // Limit description length for display
                    $preview = strlen($description) > 80 ? substr($description, 0, 80).'...' : $description;

                    return "<span class=\"text-dark\">{$preview}</span>";
                }),

            TD::make('updated_at', 'Last Updated')
                ->render(function (AppCss $css) {
                    return $css->updated_at?->format('Y-m-d H:i') ?? 'Never';
                })
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            // Actions Column
            TD::make('Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (AppCss $css) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Edit')
                            ->route('platform.customization.css.edit', urlencode($css->name))
                            ->icon('bs.pencil'),

                        Button::make('Reset')
                            ->icon('bs.arrow-clockwise')
                            ->confirm('Are you sure you want to reset this CSS to default?')
                            ->method('resetCss', ['name' => $css->name]),
                    ])),
        ];
    }
}
