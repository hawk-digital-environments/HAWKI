<?php

namespace App\Policies;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Repositories\AssistantRepository;

class AssistantPolicy
{
    public function __construct(
        private readonly AssistantRepository $repository,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Assistant $assistant): bool
    {
        return $this->repository->isVisibleTo($assistant, $user);
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

    public function feedback(User $user, Assistant $assistant): bool
    {
        return $this->view($user, $assistant);
    }

    public function favorite(User $user, Assistant $assistant): bool
    {
        return $this->view($user, $assistant);
    }
}
