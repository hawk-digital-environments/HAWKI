<?php

namespace App\Policies;
use App\Models\Assistants\Assistant;
use App\Models\User;

class AssistantPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Assistant $assistant): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assistant $assistant): bool
    {
        return $user->id === $assistant->creator_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assistant $assistant): bool
    {
        return $user->id === $assistant->creator_id;
    }

    /**
     * Determine whether the user can remix the model.
     */
    public function remix(User $user, Assistant $assistant): bool
    {
        return (bool) $assistant->allow_remix;
    }


}
