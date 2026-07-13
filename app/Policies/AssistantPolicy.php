<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assistants\Assistant;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Contracts\DefinesSensitiveIncludes;
use App\Policies\Traits\AuthorizeCreateForUserTrait;
use App\Policies\Traits\AuthorizeViewAnyForUserTrait;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Organizations\OrgMembership;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssistantPolicy implements DefinesSensitiveIncludes
{
    use HandlesAuthorization;
    use AuthorizeViewAnyForUserTrait;
    use AuthorizeCreateForUserTrait;

    public function __construct(private readonly OrgMembership $orgMembership)
    {
    }

    /**
     * Relationship include paths (schema field names) restricted to the
     * privileged tier (creator or org admin).
     */
    public const PRIVILEGED_RELATIONSHIPS = [
        'assistant_setting_values',
        'assistant_feedback',
        'assistant_review',
        'attachments',
        'ai_tools',
    ];

    /**
     * @return list<string>
     */
    public function sensitiveIncludes(): array
    {
        return self::PRIVILEGED_RELATIONSHIPS;
    }

    public function view(User $user, Assistant $assistant): bool
    {
        return $this->isVisibleTo($assistant, $user);
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
        // Remixing requires both an opt-in on the source AND visibility into
        // it: otherwise any authenticated caller could clone a draft/private
        // assistant just by knowing its id.
        return $assistant->allow_remix && $this->view($user, $assistant);
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

    /**
     * Knowledge-file uploads are creator-only, matching the update tier.
     * End users of an assistant never upload — they only consume whatever
     * the creator attached via the prompt composer.
     */
    public function uploadAttachment(User $user, Assistant $assistant): bool
    {
        return $this->update($user, $assistant);
    }

    public function deleteAttachment(User $user, Assistant $assistant): bool
    {
        return $this->update($user, $assistant);
    }

    /**
     * Gates the ?include=attachments path: only the creator or org admin
     * may inspect an assistant's knowledge files. End users never see them —
     * they only experience the files' effect through the composed system
     * prompt.
     */
    public function viewAttachments(User $user, Assistant $assistant): bool
    {
        return $this->isPrivileged($user, $assistant);
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
        return $this->isPrivileged($user, $assistant);
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
        if (\in_array($assistant->release_stage, AssistantReleaseStage::publiclyVisibleCases(), true)) {
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

        return $this->orgMembership->isAdminOf($user, $assistant->organization);
    }
}
