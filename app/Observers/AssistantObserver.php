<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Repositories\AssistantOrganizationRepository;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Populates creator/organization on new assistants and cascades attachment
 * removal on delete. Extracted from the Assistant model so the model stays
 * a pure data descriptor.
 */
class AssistantObserver
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly AssistantOrganizationRepository $organizationRepository,
    ) {
    }

    public function creating(Assistant $assistant): void
    {
        if (null !== $assistant->creator_id) {
            return;
        }

        $user = $this->currentUser();

        if (null === $user) {
            return;
        }

        $assistant->creator_id = $user->id;
        $assistant->organization_id ??= $this->organizationRepository->getForUser($user)?->id;
    }

    public function deleting(Assistant $assistant): void
    {
        // Cascade attachment deletion: the polymorphic attachments table has
        // no DB-level onDelete clause. Each Attachment::delete() fires its
        // own deleting listeners, removing the on-disk file as well.
        $assistant->attachments()->delete();
    }

    private function currentUser(): ?User
    {
        $user = $this->auth->guard()->user();

        return $user instanceof User ? $user : null;
    }
}
