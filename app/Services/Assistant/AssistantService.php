<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Events\AssistantCreated;
use App\Events\AssistantTriggerReleaseStatus;
use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Repositories\AssistantOrganizationRepository;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Event;

#[Singleton()]
readonly class AssistantService
{
    public function __construct(
        private AssistantRepository $repository,
        private AssistantOrganizationRepository $organizationRepository,
        private DatabaseManager $db,
    ) {
    }

    public function remix(Assistant $source, User $creator): Assistant
    {
        return $this->db->transaction(function () use ($source, $creator) {
            $source->load(['assistantUserPrompts', 'ai_tools', 'assistantTags', 'attachments', 'assistantVersions']);

            $organizationId = $this->organizationRepository->getForUser($creator)?->id;

            $clone = $this->repository->clone($source, $creator->id, $organizationId);

            $clone->assistantUserPrompts()->createMany($source->assistantUserPrompts->map(static fn ($prompt) => ['text' => $prompt->text])->toArray());

            $clone->assistantTags()->attach($source->assistantTags->pluck('id')->toArray());

            $sourceCreator = $source->creator;

            if (null !== $sourceCreator && $this->organizationRepository->usersShareOrganization($creator, $sourceCreator)) {
                $this->repository->syncTools($clone, $source->ai_tools->pluck('id')->toArray());
            }

            $latestVersion = $source->assistantVersions->sortByDesc('version')->first();

            if ($latestVersion) {
                $clone->assistantVersions()->create([
                    'text' => $latestVersion->text,
                    'version' => $latestVersion->version,
                    'changed_keys' => $latestVersion->changed_keys,
                ]);
            }

            foreach ($source->attachments as $attachment) {
                $clone->attachments()->create($attachment->only(['uuid', 'name', 'category', 'type', 'mime', 'user_id']));
            }

            Event::dispatch(new AssistantCreated($clone));

            return $this->repository->loadRelations($clone, ['assistantUserPrompts', 'ai_tools', 'assistantTags', 'attachments', 'assistantVersions']);
        });
    }

    public function setFavorite(Assistant $assistant, User $user, bool $isFavorite): void
    {
        $this->repository->setFavorite($assistant, $user, $isFavorite);
    }

    public function release(Assistant $assistant, AssistantReleaseStage $newStage): Assistant
    {
        $oldStage = AssistantReleaseStage::from($assistant->release_stage);

        $changed = $this->repository->setReleaseStage($assistant, $newStage);

        if ($changed) {
            Event::dispatch(new AssistantTriggerReleaseStatus($assistant, $oldStage, $newStage));
        }

        return $assistant;
    }
}
