<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Models\AppLocalizedText;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class LocalizedTextListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'localizedtexts';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('content_key', 'Content Key')
                ->sort()
                ->render(function (AppLocalizedText $localizedText) {
                    return Link::make($localizedText->content_key)
                        ->route('platform.customization.localizedtexts.edit', urlencode($localizedText->content_key));
                }),

            TD::make('description', 'Description')
                ->render(function (AppLocalizedText $localizedText) {
                    $description = $localizedText->description ?? '';
                    if (empty($description)) {
                        return '<span class="text-secondary fst-italic">No description</span>';
                    }
                    $preview = strlen($description) > 100 ? substr($description, 0, 100).'...' : $description;

                    return "<span class=\"text-dark\">{$preview}</span>";
                }),

            TD::make('languages', 'Languages')
                ->render(function (AppLocalizedText $localizedText) {
                    // Get all available languages for this content key
                    $availableLanguages = AppLocalizedText::where('content_key', $localizedText->content_key)
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

            TD::make('updated_at', 'Last Updated')
                ->render(function (AppLocalizedText $localizedText) {
                    return $localizedText->updated_at?->format('Y-m-d H:i') ?? '';
                })
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            // Actions Column
            TD::make('Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (AppLocalizedText $localizedText) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Edit')
                            ->route('platform.customization.localizedtexts.edit', urlencode($localizedText->content_key))
                            ->icon('bs.pencil'),

                        Button::make('Reset')
                            ->icon('bs.arrow-clockwise')
                            ->confirm('Are you sure you want to reset this localized text to default?')
                            ->method('resetLocalizedText', ['content_key' => $localizedText->content_key]),
                    ])),
        ];
    }
}
