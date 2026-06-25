<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Events\AssistantCreated;
use App\Events\AssistantTriggerReleaseStatus;
use App\Events\AssistantUpdated;
use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Repositories\FeedbackRepository;
use App\Services\Assistant\Repositories\OrganizationRepository;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Event;

#[Singleton]
readonly class AssistantService
{
    public function __construct(
        private AssistantRepository $repository,
        private FeedbackRepository $feedbackRepository,
        private OrganizationRepository $organizationRepository,
        private DatabaseManager $db,
    ) {}

    public function remix(Assistant $source, User $creator): Assistant
    {
        return $this->db->transaction(function () use ($source, $creator) {
            $source->load(['user_prompts', 'ai_tools', 'tags', 'attachments', 'versions']);

            $organizationId = $this->organizationRepository->getForUser($creator)?->id;

            $clone = $this->repository->clone($source, $creator->id, $organizationId);

            $clone->user_prompts()->createMany(
                $source->user_prompts->map(fn ($prompt) => ['text' => $prompt->text])->toArray()
            );

            $this->repository->syncTags($clone, $source->tags->pluck('id')->toArray());

            $sourceCreator = $source->creator;
            if ($this->organizationRepository->usersShareOrganization($creator, $sourceCreator)) {
                $this->repository->syncTools($clone, $source->ai_tools->pluck('id')->toArray());
            }

            $latestVersion = $source->versions->sortByDesc('version')->first();
            if ($latestVersion) {
                $clone->versions()->create([
                    'text' => $latestVersion->text,
                    'version' => $latestVersion->version,
                    'changed_keys' => $latestVersion->changed_keys,
                ]);
            }

            foreach ($source->attachments as $attachment) {
                $clone->attachments()->create(
                    $attachment->only(['uuid', 'name', 'category', 'type', 'mime', 'user_id'])
                );
            }

            Event::dispatch(new AssistantCreated($clone));

            return $this->repository->loadRelations($clone, ['user_prompts', 'ai_tools', 'tags', 'attachments', 'versions']);
        });
    }

    public function feedback(Assistant $assistant, User $user, string $text): void
    {
        $this->feedbackRepository->create($assistant, $user, $text);
    }

    public function release(Assistant $assistant, ReleaseStage $newStage): Assistant
    {
        $oldStage = ReleaseStage::from($assistant->release_stage);

        $changed = $this->repository->setReleaseStage($assistant, $newStage);

        if ($changed) {
            Event::dispatch(new AssistantTriggerReleaseStatus($assistant, $oldStage, $newStage));
        }

        return $assistant;
    }

    public function setFavorite(Assistant $assistant, User $user, bool $isFavorite): void
    {
        $this->repository->setFavorite($assistant, $user, $isFavorite);
    }

    public function updateSettings(Assistant $assistant, array $settings): void
    {
        $this->db->transaction(function () use ($assistant, $settings) {
            foreach ($settings as $entry) {
                $settingId = $entry['setting_id'] ?? null;

                if ($settingId === null) {
                    continue;
                }

                $assistant->settingValues()->updateOrCreate(
                    ['setting_id' => $settingId],
                    ['value' => $entry['value']],
                );
            }
        });
    }

    public function updateUserPrompts(Assistant $assistant, array $add, array $remove): void
    {
        $this->db->transaction(function () use ($assistant, $add, $remove) {
            if (! empty($remove)) {
                $this->repository->removeUserPrompts($assistant, $remove);
            }

            if (! empty($add)) {
                $this->repository->createUserPrompts($assistant, $add);
            }
        });

        if ($add !== [] || $remove !== []) {
            Event::dispatch(new AssistantUpdated($assistant, null, ['user_prompts']));
        }
    }
}
