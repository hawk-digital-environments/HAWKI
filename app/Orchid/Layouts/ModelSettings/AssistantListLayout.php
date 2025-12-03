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
                        $badgeClass = 'bg-primary';
                        $tooltipText = $assistant->aiModel->label;
                        $issues = [];
                        
                        // Check model status
                        if (!$assistant->aiModel->is_active) {
                            $issues[] = 'Model Inactive';
                            $badgeClass = 'bg-danger';
                        }
                        
                        // Only check visibility for default_model assistant
                        if ($assistant->key === 'default_model' && !$assistant->aiModel->is_visible) {
                            $issues[] = 'Default Model Must Be Visible';
                            $badgeClass = 'bg-warning';
                        }
                        
                        // Check provider status
                        if ($assistant->aiModel->provider && !$assistant->aiModel->provider->is_active) {
                            $issues[] = 'Provider Inactive';
                            $badgeClass = 'bg-danger';
                        }
                        
                        if (!empty($issues)) {
                            $tooltipText .= ' (' . implode(', ', $issues) . ')';
                        }
                        
                        return "<span class=\"badge {$badgeClass} border-0 rounded-pill\" title=\"{$tooltipText}\">{$assistant->aiModel->label}</span>";
                    }
                    return "<span class=\"badge bg-secondary border-0 rounded-pill\">No Model</span>";
                }),

            // Owner Relationship
            TD::make('owner.name', 'Owner')
                ->sort()
                ->render(function (AiAssistant $assistant) {
                    if ($assistant->owner) {
                        // Show "System" for system user (ID 1 or employeetype 'system')
                        if ($assistant->owner_id === 1 || $assistant->owner->employeetype === 'system') {
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
                    $isSystemAssistant = $assistant->owner_id === 1 || 
                                        ($assistant->owner && $assistant->owner->employeetype === 'system');
                    
                    // System assistants must always be active and non-toggleable
                    if ($isSystemAssistant) {
                        $warnings = [];
                        
                        // Check if AI Model is assigned
                        if (!$assistant->ai_model) {
                            $warnings[] = 'No AI Model';
                        } elseif ($assistant->aiModel) {
                            // Check if assigned AI Model is active
                            if (!$assistant->aiModel->is_active) {
                                $warnings[] = 'AI Model Inactive';
                            }
                            
                            // Only check visibility for default_model assistant
                            if ($assistant->key === 'default_model' && !$assistant->aiModel->is_visible) {
                                $warnings[] = 'Default Model Must Be Visible';
                            }
                            
                            // Check if the AI Model's provider is active
                            if ($assistant->aiModel->provider && !$assistant->aiModel->provider->is_active) {
                                $warnings[] = 'Provider Inactive';
                            }
                        }
                        
                        // Check system prompt (only for assistants that should have prompts)
                        if (in_array($assistant->key, ['default_model', 'title_generator', 'prompt_improver', 'summarizer']) && !$assistant->prompt) {
                            $warnings[] = 'No System Prompt';
                        }
                        
                        if (!empty($warnings)) {
                            // Override with warning status for incomplete system assistants
                            $badgeText = 'Config Issues';
                            $badgeClass = 'bg-danger';
                            $tooltipText = 'System Assistant Issues: ' . implode(', ', $warnings);
                        } else {
                            // Complete system assistant - always show active
                            $badgeText = 'Active';
                            $badgeClass = 'bg-success';
                            $tooltipText = 'System Assistant: Always active, cannot be changed';
                        }
                        
                        // Non-clickable badge for system assistants
                        return '<span class="badge ' . $badgeClass . ' border-0 rounded-pill" title="' . htmlspecialchars($tooltipText) . '">' . $badgeText . '</span>';
                    }

                    // Clickable button for user-created assistants
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
                        'group' => 'Group',
                        'private' => 'Private'
                    ];
                    $visibilityColors = [
                        'public' => 'bg-success',
                        'group' => 'bg-info',
                        'private' => 'bg-warning'
                    ];

                    // Check if this is a system assistant
                    $isSystemAssistant = $assistant->owner_id === 1 || 
                                        ($assistant->owner && $assistant->owner->employeetype === 'system');
                    
                    // System assistants must always be public and non-toggleable
                    if ($isSystemAssistant) {
                        return '<span class="badge bg-success border-0 rounded-pill" title="System Assistant: Always public, cannot be changed">Public</span>';
                    }

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
                    
                    // Check if this is a system assistant
                    $isSystemAssistant = $assistant->owner_id === 1 || 
                                        ($assistant->owner && $assistant->owner->employeetype === 'system');
                    
                    $actions = [
                        Link::make(__('Edit'))
                            ->href($editUrl)
                            ->icon('bs.pencil'),
                    ];
                    
                    // Only add toggle buttons and delete for non-system assistants
                    if (!$isSystemAssistant) {
                        $actions[] = Button::make(__('Toggle Status'))
                            ->icon('bs.toggle-on')
                            ->method('toggleStatus', ['id' => $assistant->id]);
                        
                        $actions[] = Button::make(__('Toggle Visibility'))
                            ->icon('bs.eye')
                            ->method('toggleVisibility', ['id' => $assistant->id]);
                        
                        $actions[] = Button::make(__('Delete'))
                            ->icon('bs.trash3')
                            ->confirm(__('Are you sure you want to delete this assistant?'))
                            ->method('remove', ['id' => $assistant->id]);
                    }
                    
                    return DropDown::make()
                        ->icon('bs.three-dots-vertical')
                        ->list($actions);
                }),
        ];
    }
}