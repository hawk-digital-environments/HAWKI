<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\ProviderSetting;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ProviderSettingsListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'providers';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('provider_name', 'Provider Name')
                    ->render(function (ProviderSetting $provider) {
                        return Link::make($provider->provider_name)
                        ->route('platform.models.api.providers.edit', $provider->id);
                    })
                    ->sort()
                    ->filter(),

            TD::make('api_format_id', __('API Format'))
                ->sort()
                ->filter(Input::make())
                ->render(fn (ProviderSetting $provider) => 
                    $provider->apiFormat ? $provider->apiFormat->display_name : 'Not Set'
                ),

            TD::make('base_url', __('Base URL'))
                ->render(fn (ProviderSetting $provider) => 
                    $provider->base_url ?: 'Not Set'
                )
                ->defaultHidden(),

            TD::make('is_active', __('Status'))
                ->sort()
                ->filter(Select::make()->options([
                    1 => 'Active',
                    0 => 'Inactive',
                ])->empty('All Status'))
                ->render(function (ProviderSetting $provider) {
                    $badgeText = $provider->is_active ? 'Active' : 'Inactive';
                    $badgeClass = $provider->is_active ? 'bg-success' : 'bg-secondary';
                    
                    return Button::make($badgeText)
                        ->method('toggleStatus', [
                            'id' => $provider->id,
                        ])
                        ->class("badge {$badgeClass} border-0");
                }),

            TD::make('created_at', __('Created'))
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->defaultHidden()
                ->sort(),

            TD::make('updated_at', __('Last Updated'))
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (ProviderSetting $provider) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make(__('Edit'))
                            ->route('platform.models.api.providers.edit', $provider->id)
                            ->icon('bs.pencil'),

                        Button::make(__('Test Connection'))
                            ->icon('bs.wifi')
                            ->method('testConnection', [
                                'id' => $provider->id,
                            ]),

                        Button::make(__('Delete'))
                            ->icon('bs.trash3')
                            ->confirm(__('Are you sure you want to delete this provider? This action cannot be undone and will also delete all associated language models.'))
                            ->method('deleteProvider', [
                                'id' => $provider->id,
                            ]),
                    ])),
        ];
    }
}
