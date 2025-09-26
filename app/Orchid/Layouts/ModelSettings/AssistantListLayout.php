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
                    $filterParams = request()->only([
                        'assistant_search', 
                        'assistant_status', 
                        'assistant_visibility', 
                        'assistant_owner',
                        'sort',
                        'filter'
                    ]);
                    
                    $editUrl = route('platform.models.assistants.edit', $assistant);
                    if (!empty($filterParams)) {
                        $editUrl .= '?' . http_build_query($filterParams);
                    }
                    
                    return Link::make($assistant->name)->href($editUrl);
                }),

            // Description (moved from bottom)
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

            // AI Model Relationship
            TD::make('ai_model', 'AI Model')
                ->sort()
                ->render(function (AiAssistant $assistant) {
                    if ($assistant->aiModel) {
                        return "<span class=\"badge bg-primary border-0 rounded-pill\">{$assistant->aiModel->label}</span>";
                    }
                    return "<span class=\"badge bg-secondary border-0 rounded-pill\">No Model</span>";
                }),

            // Owner Relationship
            TD::make('owner.name', 'Owner')
                ->sort()
                ->render(function (AiAssistant $assistant) {
                    if ($assistant->owner) {
                        // Show "System" for HAWKI user
                        if ($assistant->owner->name === 'HAWKI') {
                            return "<span class=\"badge bg-primary border-0 rounded-pill\">System</span>";
                        }
                        return $assistant->owner->name;
                    }
                    return "<span class=\"text-muted\">No Owner</span>";
                }),

                        // Status Column (interaktiv mit Toggle-Button, mit System-Konfigurationswarnungen)
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
                    $tooltipText = null;

                    // Check for system assistant configuration issues
                    $isSystemAssistant = $assistant->owner && $assistant->owner->name === 'HAWKI';
                    if ($isSystemAssistant) {
                        $warnings = [];
                        if (!$assistant->ai_model) {
                            $warnings[] = 'No AI Model';
                        }
                        if (!$assistant->prompt) {
                            $warnings[] = 'No System Prompt';
                        }
                        
                        if (!empty($warnings)) {
                            // Override with warning status for incomplete system assistants
                            $badgeText = 'Config Incomplete';
                            $badgeClass = 'bg-danger';
                            $tooltipText = 'System Assistant: Missing ' . implode(', ', $warnings);
                        } else {
                            // Complete system assistant - show normal status with system indicator
                            $tooltipText = 'System Assistant: Properly configured';
                        }
                    }

                    // Check if this is an incomplete system assistant (non-clickable)
                    $isIncompleteSystemAssistant = $isSystemAssistant && !empty($warnings);
                    
                    if ($isIncompleteSystemAssistant) {
                        // Non-clickable badge for incomplete system assistants
                        $badgeHtml = '<span class="badge ' . $badgeClass . ' border-0 rounded-pill">' . $badgeText . '</span>';
                        return '<span title="' . htmlspecialchars($tooltipText) . '">' . $badgeHtml . '</span>';
                    } else {
                        // Clickable button for all other cases
                        $buttonHtml = Button::make($badgeText)
                            ->method('toggleStatus', ['id' => $assistant->id])
                            ->class("badge {$badgeClass} border-0 rounded-pill");
                        
                        // Add tooltip if we have one
                        if ($tooltipText) {
                            return '<span title="' . htmlspecialchars($tooltipText) . '">' . $buttonHtml . '</span>';
                        }
                        
                        return $buttonHtml;
                    }
                }),

            // Visibility Column (interaktiv mit Toggle-Button)
            TD::make('visibility', 'Visibility')
                ->sort()
                ->render(function (AiAssistant $assistant) {
                    $visibilityLabels = [
                        'public' => 'Public',
                        'group' => 'Group',
                        'private' => 'Private'
                    ];
                    $visibilityColors = [
                        'public' => 'bg-success',
                        'group' => 'bg-info',
                        'private' => 'bg-warning'
                    ];

                    $badgeText = $visibilityLabels[$assistant->visibility] ?? 'Unknown';
                    $badgeClass = $visibilityColors[$assistant->visibility] ?? 'bg-secondary';

                    return Button::make($badgeText)
                        ->method('toggleVisibility', ['id' => $assistant->id])
                        ->class("badge {$badgeClass} border-0 rounded-pill");
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
                ->render(function (AiAssistant $assistant) {
                    $filterParams = request()->only([
                        'assistant_search', 
                        'assistant_status', 
                        'assistant_visibility', 
                        'assistant_owner',
                        'sort',
                        'filter'
                    ]);
                    
                    $editUrl = route('platform.models.assistants.edit', $assistant->id);
                    if (!empty($filterParams)) {
                        $editUrl .= '?' . http_build_query($filterParams);
                    }
                    
                    return DropDown::make()
                        ->icon('bs.three-dots-vertical')
                        ->list([
                            Link::make(__('Edit'))
                                ->href($editUrl)
                                ->icon('bs.pencil'),


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
                        ]);
                }),
        ];
    }
}