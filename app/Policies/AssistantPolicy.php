<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\Assistant;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;

class AssistantPolicy
{
    /**
     * Relationship include paths (schema field names) restricted to the
     * privileged tier (creator or org admin).
     */
    public const PRIVILEGED_RELATIONSHIPS = [
        'assistant_setting_values',
        'assistant_feedback',
        'assistant_review',
    ];

    /**
     * Relationship include paths restricted to the collaborator tier
     * (creator, org admin, or shared user; public viewers excluded).
     */
    public const COLLABORATE_RELATIONSHIPS = [
        'ai_tools',
    ];

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Assistant $assistant): bool
    {
        return $this->isVisibleTo($assistant, $user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Assistant $assistant): bool
    {
        return $user->id === $assistant->creator_id;
    }

    public function delete(User $user, Assistant $assistant): bool
    {
        return $user->id === $assistant->creator_id;
    }

    public function remix(User $user, Assistant $assistant): bool
    {
        return (bool) $assistant->allow_remix;
    }

    public function release(User $user, Assistant $assistant): bool
    {
        return $user->id === $assistant->creator_id;
    }

    public function addFavorite(User $user, Assistant $assistant): bool
    {
        return $this->view($user, $assistant);
    }

    public function removeFavorite(User $user, Assistant $assistant): bool
    {
        return $this->view($user, $assistant);
    }

    // --- Relationship visibility helpers ---

    public function viewAssistantSettingValues(User $user, Assistant $assistant): bool
    {
        return $this->isPrivileged($user, $assistant);
    }

    public function viewAssistantFeedback(User $user, Assistant $assistant): bool
    {
        return $this->isPrivileged($user, $assistant);
    }

    public function viewAssistantReview(User $user, Assistant $assistant): bool
    {
        return $this->isPrivileged($user, $assistant);
    }

    public function viewAssistantTags(User $user, Assistant $assistant): bool
    {
        return $this->canViewAssistant($user, $assistant);
    }

    public function updateAssistantTags(User $user, Assistant $assistant): bool
    {
        return $this->isPrivileged($user, $assistant);
    }

    public function attachAssistantTags(User $user, Assistant $assistant): bool
    {
        return $this->isPrivileged($user, $assistant);
    }

    public function detachAssistantTags(User $user, Assistant $assistant): bool
    {
        return $this->isPrivileged($user, $assistant);
    }

    public function viewLanguage(User $user, Assistant $assistant): bool
    {
        return $this->canViewAssistant($user, $assistant);
    }

    public function viewAssistantCategory(User $user, Assistant $assistant): bool
    {
        return $this->canViewAssistant($user, $assistant);
    }

    public function viewAssistantAvatar(User $user, Assistant $assistant): bool
    {
        return $this->canViewAssistant($user, $assistant);
    }

    public function viewAssistantUserPrompts(User $user, Assistant $assistant): bool
    {
        return $this->canViewAssistant($user, $assistant);
    }

    public function viewCreator(User $user, Assistant $assistant): bool
    {
        return $this->canViewAssistant($user, $assistant);
    }

    public function viewRemixCreator(User $user, Assistant $assistant): bool
    {
        return $this->canViewAssistant($user, $assistant);
    }

    public function viewRemixedAssistant(User $user, Assistant $assistant): bool
    {
        return $this->canViewAssistant($user, $assistant);
    }

    public function viewAssistantVersions(User $user, Assistant $assistant): bool
    {
        return $this->canViewAssistant($user, $assistant);
    }

    public function viewOrganization(User $user, Assistant $assistant): bool
    {
        return $this->canViewAssistant($user, $assistant);
    }

    public function viewSharedUsers(User $user, Assistant $assistant): bool
    {
        return $user->id === $assistant->creator_id;
    }

    public function updateSharedUsers(User $user, Assistant $assistant): bool
    {
        return $user->id === $assistant->creator_id;
    }

    public function attachSharedUsers(User $user, Assistant $assistant): bool
    {
        return $user->id === $assistant->creator_id;
    }

    public function detachSharedUsers(User $user, Assistant $assistant): bool
    {
        return $user->id === $assistant->creator_id;
    }

    public function viewAiTools(User $user, Assistant $assistant): bool
    {
        return $this->canCollaborate($user, $assistant);
    }

    public function updateAiTools(User $user, Assistant $assistant): bool
    {
        return $this->isPrivileged($user, $assistant);
    }

    public function attachAiTools(User $user, Assistant $assistant): bool
    {
        return $this->isPrivileged($user, $assistant);
    }

    public function detachAiTools(User $user, Assistant $assistant): bool
    {
        return $this->isPrivileged($user, $assistant);
    }

    // --- Internal helpers ---

    private function canViewAssistant(User $user, Assistant $assistant): bool
    {
        return $this->view($user, $assistant);
    }

    private function isVisibleTo(Assistant $assistant, User $user): bool
    {
        if (\in_array($assistant->release_stage, AssistantReleaseStage::publiclyVisibleValues(), true)) {
            return true;
        }

        if ($assistant->creator_id === $user->id) {
            return true;
        }

        return $assistant->sharedUsers()
            ->where('user_id', $user->id)
            ->exists();
    }

    private function isPrivileged(User $user, Assistant $assistant): bool
    {
        if ($assistant->creator_id === $user->id) {
            return true;
        }

        return $this->isAdminOf($user, $assistant->organization);
    }

    private function canCollaborate(User $user, Assistant $assistant): bool
    {
        if ($this->isPrivileged($user, $assistant)) {
            return true;
        }

        return $assistant->sharedUsers()
            ->where('user_id', $user->id)
            ->exists();
    }

    private function isAdminOf(User $user, ?Organization $organization): bool
    {
        if (null === $organization) {
            return false;
        }

        return $user->organizations()
            ->wherePivot('role', 'admin')
            ->where('organizations.id', $organization->id)
            ->exists();
    }
}
