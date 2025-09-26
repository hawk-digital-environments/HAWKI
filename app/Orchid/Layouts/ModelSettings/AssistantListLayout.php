<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Models\AiAssistant;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class AssistantListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'assistants';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            // Primary Identifier
            TD::make('name', 'Name')
                ->sort()
                ->cantHide()
                ->render(function (AiAssistant $assistant) {
                    return Link::make($assistant->name)
                        ->route('platform.models.assistants.edit', $assistant);
                }),

            // Secondary Identifier
            TD::make('key', 'Key')
                ->sort()
                ->cantHide()
                ->render(fn (AiAssistant $assistant) => "<code>{$assistant->key}</code>"),

            // Status Column (interaktiv mit Toggle-Button)
            TD::make('status', 'Status')
                ->sort()
                ->render(function (AiAssistant $assistant) {
                    $statusLabels = [
                        'active' => 'Active',
                        'draft' => 'Draft', 
                        'archived' => 'Archived'
                    ];
                    $statusColors = [
                        'active' => 'bg-success',
                        'draft' => 'bg-warning',
                        'archived' => 'bg-secondary'
                    ];

                    $badgeText = $statusLabels[$assistant->status] ?? 'Unknown';
                    $badgeClass = $statusColors[$assistant->status] ?? 'bg-secondary';

                    return Button::make($badgeText)
                        ->method('toggleStatus', ['id' => $assistant->id])
                        ->class("badge {$badgeClass} border-0 rounded-pill");
                }),

            // Visibility Column (interaktiv mit Toggle-Button)
            TD::make('visibility', 'Visibility')
                ->sort()
                ->render(function (AiAssistant $assistant) {
                    $visibilityLabels = [
                        'public' => 'Public',
                        'org' => 'Organization',
                        'private' => 'Private'
                    ];
                    $visibilityColors = [
                        'public' => 'bg-success',
                        'org' => 'bg-info',
                        'private' => 'bg-warning'
                    ];

                    $badgeText = $visibilityLabels[$assistant->visibility] ?? 'Unknown';
                    $badgeClass = $visibilityColors[$assistant->visibility] ?? 'bg-secondary';

                    return Button::make($badgeText)
                        ->method('toggleVisibility', ['id' => $assistant->id])
                        ->class("badge {$badgeClass} border-0 rounded-pill");
                }),

            // AI Model Relationship
            TD::make('ai_model', 'AI Model')
                ->sort()
                ->render(function (AiAssistant $assistant) {
                    if ($assistant->aiModel) {
                        return "<span class=\"badge bg-primary border-0 rounded-pill\">{$assistant->aiModel->label}</span>";
                    }
                    return "<span class=\"badge bg-secondary border-0 rounded-pill\">No Model</span>";
                }),

            // Prompt Type
            TD::make('prompt', 'Prompt Type')
                ->sort()
                ->render(function (AiAssistant $assistant) {
                    if ($assistant->prompt) {
                        return "<span class=\"badge bg-info border-0 rounded-pill\">{$assistant->prompt}</span>";
                    }
                    return "<span class=\"badge bg-secondary border-0 rounded-pill\">No Prompt</span>";
                }),

            // Owner Relationship
            TD::make('owner.name', 'Owner')
                ->sort()
                ->render(function (AiAssistant $assistant) {
                    if ($assistant->owner) {
                        return $assistant->owner->name;
                    }
                    return "<span class=\"text-muted\">No Owner</span>";
                }),

            // Description (truncated)
            TD::make('description', 'Description')
                ->render(function (AiAssistant $assistant) {
                    if (!$assistant->description) {
                        return "<span class=\"text-muted\">No description</span>";
                    }
                    
                    $truncated = strlen($assistant->description) > 60 
                        ? substr($assistant->description, 0, 60) . '...'
                        : $assistant->description;
                    
                    return "<span title=\"{$assistant->description}\">{$truncated}</span>";
                }),

            // Created At (standardmäßig versteckt)
            TD::make('created_at', __('Created'))
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->defaultHidden()
                ->sort(),

            TD::make('updated_at', __('Last Updated'))
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->sort(),

            // Actions Column
            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (AiAssistant $assistant) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        // TODO: Implement Edit functionality
                        // Link::make(__('Edit'))
                        //     ->route('platform.models.assistants.edit', $assistant->id)
                        //     ->icon('bs.pencil'),

                        Button::make(__('Toggle Status'))
                            ->icon('bs.toggle-on')
                            ->method('toggleStatus', ['id' => $assistant->id]),

                        Button::make(__('Toggle Visibility'))
                            ->icon('bs.eye')
                            ->method('toggleVisibility', ['id' => $assistant->id]),

                        Button::make(__('Delete'))
                            ->icon('bs.trash3')
                            ->confirm(__('Are you sure you want to delete this assistant?'))
                            ->method('remove', ['id' => $assistant->id]),
                    ])),
        ];
    }
}