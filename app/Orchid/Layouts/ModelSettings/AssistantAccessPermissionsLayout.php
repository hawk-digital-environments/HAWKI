<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Orchid\Fields\BadgeField;
use Orchid\Platform\Models\Role;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class AssistantAccessPermissionsLayout extends Rows
{
    /**
     * Views.
     */
    public function fields(): array
    {
        $assistant = $this->query->get('assistant');
        $isNew = !$assistant || !$assistant->exists;
        
        // Check if this is a system assistant (owner_id = 1 or employeetype = 'system') for warning system
        $isSystemAssistant = !$isNew && ($assistant->owner_id === 1 || 
                                        ($assistant->owner && $assistant->owner->employeetype === 'system'));
        $configurationWarnings = [];
        
        if ($isSystemAssistant) {
            // Check for missing configurations in system assistants
            if (!$assistant->ai_model) {
                $configurationWarnings[] = 'AI Model is missing';
            }
            if (!$assistant->prompt) {
                $configurationWarnings[] = 'System Prompt is missing';
            }
        }
        
        $fields = [];

        // Owner Display - only show for existing assistants (owner is always the creator)
        if (!$isNew) {
            // Check if owner is system user (ID 1 or employeetype 'system') and display as "System"
            $isSystemOwner = $assistant->owner_id === 1 || 
                           ($assistant->owner && $assistant->owner->employeetype === 'system');
            $ownerName = $assistant->owner->name ?? '';
            $displayName = $isSystemOwner ? 'System' : $ownerName;
            $badgeClass = $isSystemOwner ? 'bg-primary-subtle text-primary-emphasis' : 'bg-secondary-subtle text-secondary-emphasis';
            
            $fields[] = BadgeField::make('creator_display')
                ->title('Creator')
                ->value($displayName)
                ->help('The user who originally created this assistant')
                ->badgeClass($badgeClass);
                
            // System Configuration Status Badge (only for system assistants)
            if ($isSystemAssistant) {
                if (empty($configurationWarnings)) {
                    $fields[] = BadgeField::make('system_config_status')
                        ->title('System Configuration')
                        ->value('Complete')
                        ->help('AI Model and System Prompt are properly configured')
                        ->badgeClass('bg-success-subtle text-success-emphasis');
                } else {
                    $fields[] = BadgeField::make('system_config_status')
                        ->title('System Configuration')
                        ->value('⚠️ Incomplete')
                        ->help('Missing: ' . implode(', ', $configurationWarnings) . '. Please configure these for proper system operation.')
                        ->badgeClass('bg-warning-subtle text-warning-emphasis');
                }
            }
        }

        // Status field with warning for system assistants
        $statusHelp = 'Current status of the assistant';
        if ($isSystemAssistant) {
            $statusHelp = '⚠️ System assistants must always be active and cannot have their status changed.';
            if (!empty($configurationWarnings)) {
                $statusHelp .= ' Configuration incomplete: ' . implode(', ', $configurationWarnings);
            }
        }

        $statusField = Select::make('assistant.status')
            ->title('Status')
            ->options([
                'draft' => 'Draft',
                'active' => 'Active',
                'archived' => 'Archived',
            ])
            ->help($statusHelp)
            ->required();
        
        // Disable status field for system assistants
        if ($isSystemAssistant) {
            $statusField->disabled();
        }
        
        $fields[] = $statusField;

        // Visibility field
        $visibilityHelp = 'Who can access this assistant';
        if ($isSystemAssistant) {
            $visibilityHelp = '⚠️ System assistants must always be public and cannot have their visibility changed.';
        }
        
        $visibilityField = Select::make('assistant.visibility')
            ->title('Visibility')
            ->options([
                'private' => 'Private',
                'group' => 'Group (Role-based)',
                'public' => 'Public',
            ])
            ->help($visibilityHelp)
            ->required();
        
        // Disable visibility field for system assistants
        if ($isSystemAssistant) {
            $visibilityField->disabled();
        }
        
        $fields[] = $visibilityField;

        // Required Role field (always disabled for system assistants)
        $roleField = Select::make('assistant.required_role')
            ->title('Required Role')
            ->fromQuery(Role::query(), 'name', 'slug')
            ->empty('Select Role')
            ->help('Only users with this role can access the assistant (only applies when visibility is "Group")');
        
        if ($isSystemAssistant) {
            $roleField->disabled();
        }
        
        $fields[] = $roleField;

        return $fields;
    }
}