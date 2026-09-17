<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantVersion;
use App\Models\User;
use App\Services\Assistant\Events\AssistantAttachmentStoredEvent;
use App\Services\Assistant\Events\AssistantCreatedEvent;
use App\Services\Assistant\Events\AssistantReleaseStageChangedEvent;
use App\Services\Assistant\Repositories\AssistantAttachmentRepository;
use App\Services\Assistant\Repositories\AssistantOrganizationRepository;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Repositories\AssistantReviewRepository;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\AssistantReviewStatus;
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
        private AssistantAttachmentRepository $assistantAttachmentRepository,
    ) {
    }

    public function remix(Assistant $source, User $creator, ?int $organizationId = null): Assistant
    {
        return $this->db->transaction(function () use ($source, $creator, $organizationId): Assistant {
            $source->load(['assistantUserPrompts', 'ai_tools', 'assistantTags', 'assistantVersions']);

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
                $this->repository->copyCapabilities($clone, $source);
            }

            $latestVersion = $source->assistantVersions->sortByDesc('version')->first();

            if ($latestVersion) {
                // A remix starts with the same version number as the source, but an empty note.
                // The remix's first edit will increment the version.
                $clone->assistantVersions()->save((new AssistantVersion())->forceFill([
                    'text' => '',
                    'version' => $latestVersion->version,
                    'changed_keys' => $latestVersion->changed_keys,
                ]), );
            }

            // Knowledge files are NOT remixed.
            // Each assistant owns its own files (and later its own RAG dataset), so the
            // clone starts with an empty knowledge base. 
            // Otherwise a remix would need to trigger a rag workflow.
            $this->events->dispatch(new AssistantCreatedEvent($clone));

            return $this->repository->loadRelations($clone, ['assistantUserPrompts', 'ai_tools', 'assistantTags', 'assistantVersions']);
        });
    }

    public function setFavorite(Assistant $assistant, User $user, bool $isFavorite): void
    {
        $this->repository->setFavorite($assistant, $user, $isFavorite);
    }

    public function release(Assistant $assistant, AssistantReleaseStage $target, ?string $note = null): Assistant
    {
        return $this->db->transaction(function () use ($assistant, $target, $note): Assistant {
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
            $review = $assistant->assistantReview;

            // A denied review would make the release below a no-op (the request
            // validator is the primary gate there).
            // It skips the note so a blocked release cannot overwrite the version history.
            $isDenied = null !== $review && AssistantReviewStatus::DENIED === $review->status;

            // The note describes the content revision being published, so it is
            // recorded on the latest version row whichever way the release
            // resolves — an immediate stage change or a review now pending.
            if (null !== $note && !$isDenied) {
                $this->applyVersionNote($assistant, $note);
            }

            // Draft / private are freely settable: publish immediately and drop
            // any pending publication request.
            if (!$target->isPublic()) {
                return $this->applyStageChange($assistant, $target);
            }

            // A denied review blocks publication entirely; the request validator
            // is the primary gate. It's a noop here.
            if ($isDenied) {
                return $assistant;
            }

            // Downward or same-level public moves (e.g. federated -> organizational)
            // reduce exposure and need no approval.
            $isUpward = $target->order() > $oldStage->order();

            if (!$isUpward) {
                return $this->applyStageChange($assistant, $target);
            }

            // Upward move into a broader public stage. Release stage change between two
            // public stages (e.g. organizational -> federated) always needs a fresh
            // approval, regardless of the current review status. A first publish
            // from a non-public tier happens right away when already approved.
            $isEscalation = $oldStage->isPublic();
            $isApproved = null !== $review
                && AssistantReviewStatus::APPROVED === $review->status;

            if (!$isEscalation && $isApproved) {
                return $this->applyStageChange($assistant, $target);
            }

            // Not yet approved, or a release stage change that requires re-approval: record
            // the desired stage and (re)open the review as pending. The assistant
            // stays at its current stage until an admin approves it. Reopening
            // starts a fresh round, so a previous denial reason is cleared — a
            // resubmission after NEEDS_REVISION is judged on its own merits.
            $this->reviewRepository->updateOrCreateForAssistant(
                $assistant->id,
                ['status' => AssistantReviewStatus::PENDING->value, 'reason' => null],
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
        $assistantAttachment = $this->assistantAttachmentRepository->assignToAssistant(
            assistant: $assistant,
            file: $storedFile,
            user: $user,
        );

        if (null !== $assistantAttachment) {
            $this->events->dispatch(new AssistantAttachmentStoredEvent($assistant, $assistantAttachment, $user));
        }
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

    /**
     * Records the creator's release note on the latest version.
     * Locked like the update listener locks the row, so a concurrent
     * debounced edit cannot interleave within the release transaction.
     */
    private function applyVersionNote(Assistant $assistant, string $note): void
    {
        $latest = $assistant->assistantVersions()->latest('version')->lockForUpdate()->first();

        if (null === $latest) {
            // Every assistant normally gets v1.0 from the create listener; this
            // covers records that predate it.
            $assistant->assistantVersions()->save(
                (new AssistantVersion())->forceFill([
                    'text' => $note,
                    'version' => 1.0,
                ]),
            );

            return;
        }

        $latest->forceFill(['text' => $note])->save();
    }
}
