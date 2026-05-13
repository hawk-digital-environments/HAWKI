<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Events\AssistantCreated;
use App\Events\AssistantUpdated;
use App\Events\AssistantTriggerReleaseStatus;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\UserPrompt;
use App\Models\User;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Repositories\OrganizationRepository;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Event;

/**
 * @api
 */
#[Singleton]
readonly class AssistantService
{
    public function __construct(
        private AssistantRepository $repository,
        private OrganizationRepository $organizationRepository,
        private DatabaseManager $db,
    ) {}

    public function find(Assistant $assistant): Assistant
    {
        return $assistant;
    }

    public function create(array $data, User $creator): Assistant
    {
        return $this->db->transaction(function () use ($data, $creator) {
            $assistantData = collect($data)
                ->except(['user_prompt_ids', 'ai_tool_ids', 'tag_ids', 'category_id', 'language_id'])
                ->merge([
                    'creator_id' => $creator->id,
                    'remixed_creator_id' => null,
                    'category_id' => $data['category_id'] ?? null,
                    'language_id' => $data['language_id'] ?? null,
                    'organization_id' => $this->organizationRepository->getForUser($creator)?->id,
                ])
                ->toArray();

            $assistant = $this->repository->create($assistantData);

            $this->handleUserPrompts($assistant, $data['user_prompt_ids'] ?? null);
            $this->handleAiTools($assistant, $data['ai_tool_ids'] ?? null);
            $this->handleTags($assistant, $data['tag_ids'] ?? null);

            Event::dispatch(new AssistantCreated($assistant));

            return $this->repository->loadRelations($assistant, ['user_prompts', 'ai_tools', 'tags']);
        });
    }

    public function update(Assistant $assistant, array $data): Assistant
    {
        return $this->db->transaction(function () use ($assistant, $data) {
            $versionText = $data['version_text'] ?? null;

            $assistantFields = collect($data)
                ->except(['user_prompt_ids', 'ai_tool_ids', 'tag_ids', 'version_text', 'category_id', 'language_id'])
                ->toArray();

            if (isset($data['category_id'])) {
                $assistantFields['category_id'] = $data['category_id'];
            }

            if (isset($data['language_id'])) {
                $assistantFields['language_id'] = $data['language_id'];
            }

            $changedKeys = array_values(array_filter(
                array_keys($this->repository->update($assistant, $assistantFields)),
                fn (string $key) => $key !== 'updated_at',
            ));

            if ($this->handleUserPrompts($assistant, $data['user_prompt_ids'] ?? null, true)) {
                $changedKeys[] = 'user_prompts';
            }

            if ($this->handleAiTools($assistant, $data['ai_tool_ids'] ?? null)) {
                $changedKeys[] = 'ai_tools';
            }

            if ($this->handleTags($assistant, $data['tag_ids'] ?? null)) {
                $changedKeys[] = 'tags';
            }

            if ($changedKeys !== []) {
                Event::dispatch(new AssistantUpdated($assistant, $versionText, $changedKeys));
            }

            return $this->repository->loadRelations($assistant, ['user_prompts', 'ai_tools', 'tags']);
        });
    }

    public function delete(Assistant $assistant): void
    {
        $this->repository->delete($assistant);
    }

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

    public function release(Assistant $assistant, ReleaseStage $newStage): Assistant
    {
        $oldStage = ReleaseStage::from($assistant->release_stage);

        $changed = $this->repository->setReleaseStage($assistant, $newStage);

        if ($changed) {
            Event::dispatch(new AssistantTriggerReleaseStatus($assistant, $oldStage, $newStage));
        }

        return $assistant;
    }

    private function handleUserPrompts(Assistant $assistant, ?array $promptIds, bool $replace = false): bool
    {
        if ($promptIds === null) {
            return false;
        }

        if ($replace) {
            return $this->repository->replaceUserPrompts($assistant, $promptIds);
        }

        UserPrompt::whereIn('id', $promptIds)
            ->update(['assistant_id' => $assistant->id]);

        return true;
    }

    private function handleAiTools(Assistant $assistant, ?array $toolIds): bool
    {
        if ($toolIds === null) {
            return false;
        }

        $result = $this->repository->syncTools($assistant, $toolIds);

        return $result['attached'] !== [] || $result['detached'] !== [] || $result['updated'] !== [];
    }

    private function handleTags(Assistant $assistant, ?array $tagIds): bool
    {
        if ($tagIds === null) {
            return false;
        }

        $result = $this->repository->syncTags($assistant, $tagIds);

        return $result['attached'] !== [] || $result['detached'] !== [] || $result['updated'] !== [];
    }
}
