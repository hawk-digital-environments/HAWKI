<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantVersion;
use App\Models\User;
use App\Services\Assistant\Events\AssistantCreatedEvent;
use App\Services\Assistant\Events\AssistantReleaseStageChangedEvent;
use App\Services\Assistant\Repositories\AssistantOrganizationRepository;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Repositories\AssistantReviewRepository;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
use App\Services\Chat\Attachment\Repositories\AttachmentRepository;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\StoredFileCategory;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\DatabaseManager;

#[Singleton()]
readonly class AssistantService
{
    public function __construct(
        private AssistantRepository $repository,
        private AssistantOrganizationRepository $organizationRepository,
        private AssistantReviewRepository $reviewRepository,
        private DatabaseManager $db,
        private EventDispatcher $events,
        private FileStorageService $fileStorage,
        private AttachmentRepository $attachmentRepository,
    ) {
    }

    public function remix(Assistant $source, User $creator, ?int $organizationId = null): Assistant
    {
        return $this->db->transaction(function () use ($source, $creator, $organizationId): Assistant {
            $source->load(['assistantUserPrompts', 'ai_tools', 'assistantTags', 'attachments', 'assistantVersions']);

            $resolvedOrgId = null !== $organizationId
                ? ($this->organizationRepository->getForUserById($creator, $organizationId)->id
                    ?? throw \App\Services\Assistant\Exceptions\OrganizationNotFoundException::forUserAndId($creator, $organizationId))
                : $this->organizationRepository->getForUser($creator)?->id;

            $clone = $this->repository->clone($source, $creator->id, $resolvedOrgId);

            $clone->assistantUserPrompts()->createMany($source->assistantUserPrompts->map(static fn ($prompt) => ['text' => $prompt->text])->toArray());

            $clone->assistantTags()->attach($source->assistantTags->pluck('id')->toArray());

            $sourceCreator = $source->creator;

            if (null !== $sourceCreator && $this->organizationRepository->usersShareOrganization($creator, $sourceCreator)) {
                $this->repository->syncTools($clone, $source->ai_tools->pluck('id')->toArray());
            }

            $latestVersion = $source->assistantVersions->sortByDesc('version')->first();

            if ($latestVersion) {
                // version is server-controlled and intentionally not mass-assignable.
                $clone->assistantVersions()->save((new AssistantVersion())->forceFill([
                    'text' => $latestVersion->text,
                    'version' => $latestVersion->version,
                    'changed_keys' => $latestVersion->changed_keys,
                ]), );
            }

            foreach ($source->attachments as $attachment) {
                $clone->attachments()->create($attachment->only(['uuid', 'name', 'category', 'type', 'mime', 'user_id']));
            }

            $this->events->dispatch(new AssistantCreatedEvent($clone));

            return $this->repository->loadRelations($clone, ['assistantUserPrompts', 'ai_tools', 'assistantTags', 'attachments', 'assistantVersions']);
        });
    }

    public function setFavorite(Assistant $assistant, User $user, bool $isFavorite): void
    {
        $this->repository->setFavorite($assistant, $user, $isFavorite);
    }

    public function release(Assistant $assistant, AssistantReleaseStage $target): Assistant
    {
        return $this->db->transaction(function () use ($assistant, $target): Assistant {
            // Re-load inside the transaction under a row lock so concurrent
            // release()/approve()/deny() calls serialise on the same assistant.
            $locked = Assistant::whereKey($assistant->id)->lockForUpdate()->first();

            if (null === $locked) {
                return $assistant;
            }

            // Refresh the in-memory state from the locked row, then run the
            // transition logic against the freshest stage/review values.
            $assistant->setRawAttributes($locked->getAttributes());
            $assistant->load('assistantReview');

            $oldStage = $assistant->release_stage;

            // Draft / private are freely settable: publish immediately and drop
            // any pending publication request.
            if (!$target->isPublic()) {
                return $this->applyStageChange($assistant, $target);
            }

            $review = $assistant->assistantReview;

            // A denied review blocks publication entirely; the request validator
            // is the primary gate, this is a defensive no-op for direct callers.
            if (null !== $review && AssistantReviewStatus::DENIED === $review->status) {
                return $assistant;
            }

            // Downward or same-level public moves (e.g. federated -> organizational)
            // reduce exposure and need no approval.
            $isUpward = $target->order() > $oldStage->order();

            if (!$isUpward) {
                return $this->applyStageChange($assistant, $target);
            }

            // Upward move into a broader public stage. Escalation between two
            // public stages (e.g. organizational -> federated) always needs a fresh
            // approval, regardless of the current review status. A first publish
            // from a non-public tier happens right away when already approved.
            $isEscalation = $oldStage->isPublic();
            $isApproved = null !== $review
                && AssistantReviewStatus::APPROVED === $review->status;

            if (!$isEscalation && $isApproved) {
                return $this->applyStageChange($assistant, $target);
            }

            // Not yet approved, or an escalation that requires re-approval: record
            // the desired stage and (re)open the review as pending. The assistant
            // stays at its current stage until an admin approves it.
            $this->reviewRepository->updateOrCreateForAssistant(
                $assistant->id,
                ['status' => AssistantReviewStatus::PENDING->value],
            );
            $this->repository->setRequestedReleaseStage($assistant, $target);

            return $assistant;
        });
    }

    /**
     * On review approval, promote the assistant to its previously requested
     * public stage, if any. Clears the request and dispatches the release
     * status event.
     */
    public function promoteRequested(Assistant $assistant): Assistant
    {
        $requested = $assistant->requested_release_stage;

        if (!$requested instanceof AssistantReleaseStage || !\in_array($requested, AssistantReleaseStage::publiclyVisibleCases(), true)) {
            return $assistant;
        }

        return $this->applyStageChange($assistant, $requested);
    }

    /**
     * On review denial, push the assistant back to private and clear any
     * pending publication request. Dispatches the release status event.
     */
    public function revokeRelease(Assistant $assistant): Assistant
    {
        return $this->applyStageChange($assistant, AssistantReleaseStage::PRIVATE);
    }

    /**
     * Handle upload of an attachment for an assistant.
     */
    public function uploadAttachment(FileReference $file, Assistant $assistant, User $user): void
    {
        $storedFile = $this->fileStorage->store(
            file: $file,
            category: StoredFileCategory::ASSISTANT,
        );
        $this->attachmentRepository->assignToAssistant(
            assistant: $assistant,
            file: $storedFile,
            user: $user,
        );
    }

    /**
     * Applies a release stage transition: sets the stage, clears any pending
     * request, and dispatches the release status event when the stage actually
     * changed.
     */
    private function applyStageChange(Assistant $assistant, AssistantReleaseStage $target): Assistant
    {
        $oldStage = $assistant->release_stage;
        $changed = $this->repository->setReleaseStage($assistant, $target);
        $this->repository->clearRequestedReleaseStage($assistant);

        if ($changed) {
            $this->events->dispatch(new AssistantReleaseStageChangedEvent($assistant, $oldStage, $target));
        }

        return $assistant;
    }
}
