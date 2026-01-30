<?php

namespace App\Orchid\Layouts\Settings;

use App\Models\Records\UsageRecord;
use App\Orchid\Traits\ApiFormatColorTrait;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class UsageDebugLayout extends Table
{
    use ApiFormatColorTrait;
    /**
     * Data source.
     *
     * @var string
     */
    protected $target = 'usage_records';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('id', 'ID')
                ->width('80px')
                ->sort()
                ->cantHide()
                ->render(function ($record) {
                    return $record->id;
                }),

            TD::make('user_id', 'User ID')
                //->width('100px')
                ->sort()
                ->render(function ($record) {
                    return $record->user_id ?? '<span class="text-muted">N/A</span>';
                }),

            TD::make('type', 'Type')
                ->width('120px')
                ->sort()
                ->render(function ($record) {
                    $badges = [
                        'private' => '<span class="badge bg-primary">Private</span>',
                        'group' => '<span class="badge bg-success">Group</span>',
                        'api' => '<span class="badge bg-info">API</span>',
                        'title' => '<span class="badge bg-secondary">Title</span>',
                        'improver' => '<span class="badge bg-secondary">Improver</span>',
                        'summarizer' => '<span class="badge bg-secondary">Summarizer</span>',
                    ];

                    return $badges[$record->type] ?? '<span class="badge bg-secondary">'.ucfirst($record->type).'</span>';
                }),

            TD::make('api_provider', 'Provider')
                ->width('120px')
                ->sort()
                ->render(function ($record) {
                    if (!$record->api_provider) {
                        return '<span class="text-muted">N/A</span>';
                    }
                    
                    // Find the provider in the database to get the API format
                    $provider = \App\Models\ApiProvider::where('unique_name', $record->api_provider)->first();
                    
                    if ($provider && $provider->apiFormat) {
                        // Use the same color logic as AiModelListLayout
                        $badgeColor = $this->getApiFormatBadgeColor($provider->apiFormat->id);
                        return $this->getProviderBadge($provider->provider_name, $badgeColor);
                    }
                    
                    // Fallback: display unique_name with secondary color
                    return $this->getSimpleBadge($record->api_provider, 'secondary');
                }),

            TD::make('model', 'Model')
                ->sort()
                ->render(function ($record) {
                    return '<code>'.$record->model.'</code>';
                }),

            TD::make('prompt_tokens', 'Prompt Tokens')
                ->width('130px')
                ->sort()
                ->render(function ($record) {
                    return '<span class="text-end d-block">'.number_format($record->prompt_tokens).'</span>';
                }),

            TD::make('completion_tokens', 'Completion Tokens')
                ->width('150px')
                ->sort()
                ->render(function ($record) {
                    return '<span class="text-end d-block">'.number_format($record->completion_tokens).'</span>';
                }),

            TD::make('total_tokens', 'Total Tokens')
                ->width('120px')
                ->render(function ($record) {
                    $total = $record->prompt_tokens + $record->completion_tokens;
                    return '<span class="text-end d-block"><strong>'.number_format($total).'</strong></span>';
                }),

            TD::make('created_at', 'Created At')
                ->width('170px')
                ->sort()
                ->render(function ($record) {
                    return '<small>'.$record->created_at->format('d.m.Y H:i:s').'</small>';
                }),
        ];
    }
}
