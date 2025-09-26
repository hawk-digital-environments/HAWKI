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
        
        // Check if this is a system assistant (owner = HAWKI) for warning system - MOVED UP
        $isSystemAssistant = !$isNew && $assistant->owner && $assistant->owner->name === 'HAWKI';
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
            // Check if owner is HAWKI (system user) and display as "System"
            $ownerName = $assistant->owner->name ?? '';
            $displayName = ($ownerName === 'HAWKI') ? 'System' : $ownerName;
            $badgeClass = ($ownerName === 'HAWKI') ? 'bg-primary-subtle text-primary-emphasis' : 'bg-secondary-subtle text-secondary-emphasis';
            
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
                        ->value('✅ Complete')
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
        if ($isSystemAssistant && !empty($configurationWarnings)) {
            $statusHelp .= ' - ⚠️ Configuration incomplete: ' . implode(', ', $configurationWarnings);
        }

        $fields = array_merge($fields, [
            Select::make('assistant.status')
                ->title('Status')
                ->options([
                    'draft' => 'Draft',
                    'active' => 'Active',
                    'archived' => 'Archived',
                ])
                ->help($statusHelp)
                ->required(),

            Select::make('assistant.visibility')
                ->title('Visibility')
                ->options([
                    'private' => 'Private',
                    'group' => 'Group (Role-based)',
                    'public' => 'Public',
                ])
                ->help('Who can access this assistant')
                ->required(),
        ]);

        // Required Role field
        $fields[] = Select::make('assistant.required_role')
            ->title('Required Role')
            ->fromQuery(Role::query(), 'name', 'slug')
            ->empty('Select Role')
            ->help('Only users with this role can access the assistant (only applies when visibility is "Group")');

        return $fields;
    }
}