<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\ApiProvider;
use App\Orchid\Traits\ApiFormatColorTrait;
use Carbon\Carbon;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ApiProvidersListLayout extends Table
{
    use ApiFormatColorTrait;

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
            TD::make('display_order', __('Order'))
                ->width('80px')
                ->align(TD::ALIGN_CENTER)
                ->render(function (ApiProvider $provider) {
                    return '<span class="badge bg-light text-dark">' . $provider->display_order . '</span>';
                })
                ->sort(),

            TD::make('provider_name', 'Provider Name')
                ->render(function (ApiProvider $provider) {
                    return Link::make($provider->provider_name)
                        ->route('platform.models.api.providers.edit', $provider->id);
                })
                ->sort(),

            TD::make('api_format_id', __('API Format'))
                ->sort()
                ->render(function (ApiProvider $provider) {
                    return $this->getApiFormatBadge($provider->apiFormat);
                }),

            TD::make('is_active', __('Status'))
                ->sort()
                ->render(function (ApiProvider $provider) {
                    $badgeText = $provider->is_active ? 'Active' : 'Inactive';
                    $badgeClass = $provider->is_active ? 'bg-success' : 'bg-secondary';
                    $tooltipText = null;
                    $warnings = [];

                    // Check for configuration issues
                    if ($provider->is_active) {
                        // Check if API key is missing
                        $isOllamaProvider = stripos($provider->provider_name, 'ollama') !== false;
                        
                        if (empty($provider->api_key)) {
                            if ($isOllamaProvider) {
                                // Ollama doesn't require API key, but show it in status
                                $tooltipText = 'No API Key (Ollama - not required)';
                            } else {
                                // Other providers require API key
                                $warnings[] = 'No API Key';
                            }
                        }
                        
                        // Check if base URL is missing
                        if (empty($provider->base_url)) {
                            $warnings[] = 'No Base URL';
                        }
                        
                        // Check if API format is missing
                        if (!$provider->apiFormat) {
                            $warnings[] = 'No API Format';
                        }
                        
                        // Override status if there are configuration issues
                        if (!empty($warnings)) {
                            $badgeText = 'Config Issues';
                            $badgeClass = 'bg-warning';
                            $tooltipText = 'Configuration Issues: ' . implode(', ', $warnings);
                        }
                    }

                    $button = Button::make($badgeText)
                        ->method('toggleStatus', [
                            'id' => $provider->id,
                        ])
                        ->class("badge {$badgeClass} border-0 rounded-pill");
                    
                    // Add tooltip if we have one
                    if ($tooltipText) {
                        return '<span title="' . htmlspecialchars($tooltipText) . '">' . $button . '</span>';
                    }
                    
                    return $button;
                }),

            TD::make('connection_status', __('Connection'))
                ->render(function (ApiProvider $provider) {
                    if (! $provider->is_active) {
                        return '<span class="badge bg-light text-muted">N/A</span>';
                    }

                    // Check for stored connection test results
                    $additionalSettings = $provider->additional_settings ?? [];
                    $lastTest = $additionalSettings['last_connection_test'] ?? null;
                    
                    if ($lastTest) {
                        $timestamp = Carbon::parse($lastTest['timestamp']);
                        $timeDiff = $timestamp->diffForHumans();
                        $responseTime = $lastTest['response_time_ms'] ?? 0;
                        
                        if ($lastTest['success']) {
                            $badgeText = "{$responseTime}ms";
                            $badgeClass = 'bg-success';
                            $tooltipText = "Last test: {$timeDiff} - Success ({$responseTime}ms)";
                        } else {
                            $badgeText = 'Failed';
                            $badgeClass = 'bg-danger';
                            $error = $lastTest['error'] ?? 'Unknown error';
                            $tooltipText = "Last test: {$timeDiff} - Failed: {$error}";
                        }
                        
                        return '<span class="badge ' . $badgeClass . '" title="' . htmlspecialchars($tooltipText) . '">' . $badgeText . '</span>';
                    }

                    // No test results available
                    return '<span class="badge bg-info" title="Click Test Connection to check">Unknown</span>';
                })
                ->cantHide(),

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
                ->render(fn (ApiProvider $provider) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make(__('Edit'))
                            ->route('platform.models.api.providers.edit', $provider->id)
                            ->icon('bs.pencil'),

                        Button::make(__('Test Connection'))
                            ->icon('bs.wifi')
                            ->method('testProviderConnection', [
                                'id' => $provider->id,
                            ])
                            ->canSee($provider->is_active),

                        Button::make(__('Get Models'))
                            ->icon('bs.cloud-download')
                            ->method('fetchProviderModels', [
                                'id' => $provider->id,
                            ])
                            ->canSee($provider->is_active),

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
