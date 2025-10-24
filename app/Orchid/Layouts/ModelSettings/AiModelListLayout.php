<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\AiModel;
use App\Orchid\Traits\ApiFormatColorTrait;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class AiModelListLayout extends Table
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
                ->render(function (AiModel $model) {
                    $queryParams = request()->only(['provider_filter', 'active_status', 'visible_status', 'search', 'date_range']);
                    $url = route('platform.models.language.edit', $model->id);
                    
                    if (!empty($queryParams)) {
                        $url .= '?' . http_build_query($queryParams);
                    }
                    
                    return Link::make($model->label)->href($url);
                }),

            TD::make('provider_name', 'Provider')
                ->sort()
                ->render(function (AiModel $model) {
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
                ->render(function (AiModel $model) {
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
                ->render(function (AiModel $model) {
                    $badgeText = $model->is_visible ? 'Visible' : 'Hidden';
                    $badgeClass = $model->is_visible ? 'bg-info' : 'bg-secondary';

                    return Button::make($badgeText)
                        ->method('toggleVisible', [
                            'id' => $model->id,
                        ])
                        ->class("badge {$badgeClass} border-0 rounded-pill");
                }),

            TD::make('capabilities', 'Capabilities')
                ->render(function (AiModel $model) {
                    $tools = $model->settings['tools'] ?? [];
                    $icons = [];

                    // File Upload capability
                    if (!empty($tools['file_upload'])) {
                        $icons[] = '<span class="badge bg-primary rounded-circle me-1" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="File Upload Support">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-paperclip" viewBox="0 0 16 16">
                                          <path d="M4.5 3a2.5 2.5 0 0 1 5 0v9a1.5 1.5 0 0 1-3 0V5a.5.5 0 0 1 1 0v7a.5.5 0 0 0 1 0V3a1.5 1.5 0 1 0-3 0v9a2.5 2.5 0 0 0 5 0V5a.5.5 0 0 1 1 0v7a3.5 3.5 0 1 1-7 0z"/>
                                        </svg>
                                     </span>';
                    }

                    // Vision capability
                    if (!empty($tools['vision'])) {
                        $icons[] = '<span class="badge bg-warning rounded-circle me-1" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="Vision/Image Analysis">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                          <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                          <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                                        </svg>
                                     </span>';
                    }

                    // Web Search capability
                    if (!empty($tools['web_search'])) {
                        $icons[] = '<span class="badge bg-success rounded-circle me-1" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="Web Search Integration">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-globe" viewBox="0 0 16 16">
                                          <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m7.5-6.923c-.67.204-1.335.82-1.887 1.855A8 8 0 0 0 5.145 4H7.5zM4.09 4a9.3 9.3 0 0 1 .64-1.539 7 7 0 0 1 .597-.933A7.0 7.0 0 0 0 2.255 4zm-.582 3.5c.03-.877.138-1.718.312-2.5H1.674a7 7 0 0 0-.656 2.5zM4.847 5a12.5 12.5 0 0 0-.338 2.5H7.5V5zM8.5 5v2.5h2.99a12.5 12.5 0 0 0-.337-2.5zM4.51 8.5a12.5 12.5 0 0 0 .337 2.5H7.5V8.5zm3.99 0V11h2.653c.187-.765.306-1.608.338-2.5zM5.145 12q.208.58.468 1.068c.552 1.035 1.218 1.65 1.887 1.855V12zm.182 2.472a7 7 0 0 1-.597-.933A9.3 9.3 0 0 1 4.09 12H2.255a7 7 0 0 0 3.072 2.472M3.82 11a13.7 13.7 0 0 1-.312-2.5h-2.49c.062.89.291 1.733.656 2.5zm6.853 3.472A7 7 0 0 0 13.745 12H11.91a9.3 9.3 0 0 1-.64 1.539 7 7 0 0 1-.597.933M8.5 12v2.923c.67-.204 1.335-.82 1.887-1.855q.26-.487.468-1.068zm3.68-1h2.146c.365-.767.594-1.61.656-2.5h-2.49a13.7 13.7 0 0 1-.312 2.5m2.802-3.5a7 7 0 0 0-.656-2.5H12.18c.174.782.282 1.623.312 2.5zM11.27 2.461c.247.464.462.98.64 1.539h1.835a7 7 0 0 0-3.072-2.472c.218.284.418.598.597.933M10.855 4a8 8 0 0 0-.468-1.068C9.835 1.897 9.17 1.282 8.5 1.077V4z"/>
                                        </svg>
                                     </span>';
                    }

                    // If no capabilities, show default icon
                    if (empty($icons)) {
                        return '<span class="badge bg-secondary rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="Text Only">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-chat-text" viewBox="0 0 16 16">
                                      <path d="M2.678 11.894a1 1 0 0 1 .287.801 11 11 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8 8 0 0 0 8 14c3.996 0 7-2.807 7-6s-3.004-6-7-6-7 2.808-7 6c0 1.468.617 2.83 1.678 3.894m-.493 3.905a22 22 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a10 10 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105"/>
                                      <path d="M4 5.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8m0 2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5"/>
                                    </svg>
                                </span>';
                    }

                    return implode('', $icons);
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
                ->render(fn (AiModel $model) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make('Edit Model')
                            ->href(route('platform.models.language.edit', $model->id) . 
                                (!empty(request()->only(['provider_filter', 'active_status', 'visible_status', 'search', 'date_range'])) 
                                    ? '?' . http_build_query(request()->only(['provider_filter', 'active_status', 'visible_status', 'search', 'date_range'])) 
                                    : ''))
                            ->icon('bs.pencil'),

                        Button::make('Make Default')
                            ->icon('bs.stars')
                            ->confirm("Are you sure you want to set '{$model->label}' as the default model for ALL AI assistants? This will update all existing assistants.")
                            ->method('setAsDefaultForAssistants', [
                                'id' => $model->id,
                            ])
                            ->canSee($model->is_active),

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
