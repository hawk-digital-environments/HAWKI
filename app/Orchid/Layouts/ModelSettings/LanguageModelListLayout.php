<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\LanguageModel;
use App\Orchid\Traits\ApiFormatColorTrait;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class LanguageModelListLayout extends Table
{
    use ApiFormatColorTrait;

    /**
     * @var string
     */
    public $target = 'models';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('label', 'Name')
                ->sort()
                ->render(function (LanguageModel $model) {
                    return Link::make($model->label)
                        ->route('platform.models.language.edit', $model->id);
                }),

            TD::make('provider_name', 'Provider')
                ->sort()
                ->render(function (LanguageModel $model) {
                    $providerName = $model->provider->provider_name ?? 'Unknown Provider';
                    $apiFormat = $model->provider->apiFormat ?? null;
                    
                    // Use trait method for consistent styling with rounded pills and larger text
                    if ($apiFormat) {
                        $badgeColor = $this->getApiFormatBadgeColor($apiFormat->id);
                        return $this->getProviderBadge($providerName, $badgeColor);
                    }
                    
                    return $this->getProviderBadge($providerName, 'secondary');
                }),

            TD::make('is_active', 'Active')
                ->sort()
                ->render(function (LanguageModel $model) {
                    $badgeText = $model->is_active ? 'Active' : 'Inactive';
                    $badgeClass = $model->is_active ? 'bg-success' : 'bg-secondary';
                    
                    return Button::make($badgeText)
                        ->method('toggleActive', [
                            'id' => $model->id,
                        ])
                        ->class("badge {$badgeClass} border-0 rounded-pill");
                }),

            TD::make('is_visible', 'Visible')
                ->sort()
                ->render(function (LanguageModel $model) {
                    $badgeText = $model->is_visible ? 'Visible' : 'Hidden';
                    $badgeClass = $model->is_visible ? 'bg-info' : 'bg-secondary';
                    
                    return Button::make($badgeText)
                        ->method('toggleVisible', [
                            'id' => $model->id,
                        ])
                        ->class("badge {$badgeClass} border-0 rounded-pill");
                }),

            TD::make('created_at', 'Created')
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->defaultHidden()
                ->sort(),

            TD::make('updated_at', 'Last Updated')
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            TD::make('Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('120px')
                ->render(fn (LanguageModel $model) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Edit Model')
                            ->route('platform.models.language.edit', $model->id)
                            ->icon('bs.pencil'),

                        Button::make('Delete')
                            ->icon('bs.trash3')
                            ->confirm("Are you sure you want to delete model '{$model->label}'?")
                            ->method('deleteModel', [
                                'id' => $model->id,
                            ]),
                    ])),
        ];
    }
}
