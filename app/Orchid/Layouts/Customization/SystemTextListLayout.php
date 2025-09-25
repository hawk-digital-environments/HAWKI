<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Models\AppSystemText;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class SystemTextListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'systemtexts';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('content_key', 'Content Key')
                ->sort()
                ->cantHide()
                ->render(function (AppSystemText $systemText) {
                    return Link::make($systemText->content_key)
                        ->route('platform.customization.systemtexts.edit', ['systemText' => $systemText->content_key]);
                }),

            TD::make('content', 'Content')
                ->render(function (AppSystemText $systemText) {
                    $content = $systemText->content ?? '';
                    if (empty($content)) {
                        return '<span class="text-secondary fst-italic">No content</span>';
                    }
                    $preview = strlen($content) > 80 ? substr($content, 0, 80).'...' : $content;

                    return "<span class=\"text-dark\">{$preview}</span>";
                }),

            TD::make('languages', 'Languages')
                ->render(function (AppSystemText $systemText) {
                    // Get all available languages for this content key
                    $availableLanguages = AppSystemText::where('content_key', $systemText->content_key)
                        ->pluck('language')
                        ->map(function ($lang) {
                            return match ($lang) {
                                'de_DE' => ['code' => 'DE', 'class' => 'bg-primary'],
                                'en_US' => ['code' => 'EN', 'class' => 'bg-success'],
                                default => ['code' => strtoupper(substr($lang, 0, 2)), 'class' => 'bg-secondary'],
                            };
                        })
                        ->sortBy('code');

                    $badges = $availableLanguages->map(function ($lang) {
                        return "<span class=\"badge rounded-pill {$lang['class']} me-1\">{$lang['code']}</span>";
                    })->implode('');

                    return $badges ?: '<span class="text-muted">No languages</span>';
                })
                ->align(TD::ALIGN_CENTER)
                ->width('120px'),

            // TD::make('language', 'Language')
            //    ->render(function (AppSystemText $systemText) {
            //        return $systemText->language === 'de_DE' ? 'Deutsch' : 'English';
            //    })
            //    ->sort(),

            TD::make('updated_at', 'Last Updated')
                ->render(function (AppSystemText $systemText) {
                    return $systemText->updated_at?->format('Y-m-d H:i') ?? '';
                })
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            // Actions Column
            TD::make('Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (AppSystemText $systemText) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Edit')
                            ->route('platform.customization.systemtexts.edit', ['systemText' => $systemText->content_key])
                            ->icon('bs.pencil'),

                        Button::make('Reset')
                            ->icon('bs.arrow-clockwise')
                            ->confirm('Are you sure you want to reset this system text to default?')
                            ->method('resetSystemText', ['content_key' => $systemText->content_key]),
                    ])),
        ];
    }
}
