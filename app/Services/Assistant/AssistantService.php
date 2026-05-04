<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Events\AssistantCreated;
use App\Events\AssistantUpdated;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Repositories\TagRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

/**
 * @api
 */
#[Singleton]
readonly class AssistantService
{
    public function __construct(
        private AssistantRepository $repository,
        private TagRepository $tagRepository,
        private DatabaseManager $db,
    ) {}

    public function list(array $relations = []): Collection
    {
        $assistants = $this->repository->all();

        if ($relations !== []) {
            $assistants->load($relations);
        }

        return $assistants;
    }

    public function find(Assistant $assistant, array $relations = []): Assistant
    {
        if ($relations !== []) {
            $this->repository->loadRelations($assistant, $relations);
        }

        return $assistant;
    }

    public function create(array $data, int $creatorId): Assistant
    {
        return $this->db->transaction(function () use ($data, $creatorId) {
            $assistant = $this->repository->create(array_merge($data, [
                'creator_id' => $creatorId,
                'remixed_creator_id' => null,
            ]));

            $this->handleUserPrompts($assistant, $data['user_prompts'] ?? null);
            $this->handleAiTools($assistant, $data['ai_tools'] ?? null);
            $this->handleTags($assistant, $data['tags'] ?? null);

            Event::dispatch(new AssistantCreated($assistant));

            return $this->repository->loadRelations($assistant, ['userPrompts', 'aiTools', 'tags']);
        });
    }

    public function update(Assistant $assistant, array $data): Assistant
    {
        return $this->db->transaction(function () use ($assistant, $data) {
            $versionText = $data['version_text'] ?? null;

            $assistantFields = collect($data)
                ->except(['user_prompts', 'ai_tools', 'tags', 'version_text'])
                ->toArray();

            $this->repository->update($assistant, $assistantFields);

            $this->handleUserPrompts($assistant, $data['user_prompts'] ?? null, true);
            $this->handleAiTools($assistant, $data['ai_tools'] ?? null);
            $this->handleTags($assistant, $data['tags'] ?? null);

            Event::dispatch(new AssistantUpdated($assistant, $versionText));

            return $this->repository->loadRelations($assistant, ['userPrompts', 'aiTools', 'tags']);
        });
    }

    public function delete(Assistant $assistant): void
    {
        $this->repository->delete($assistant);
    }

    public function remix(Assistant $source, int $creatorId): Assistant
    {
        return $this->db->transaction(function () use ($source, $creatorId) {
            $source->load(['userPrompts', 'aiTools', 'tags']);

            $clone = $this->repository->clone($source, $creatorId);

            $clone->userPrompts()->createMany(
                $source->userPrompts->map(fn ($prompt) => ['text' => $prompt->text])->toArray()
            );
            $this->repository->syncTools($clone, $source->aiTools->pluck('id')->toArray());
            $this->repository->syncTags($clone, $source->tags->pluck('id')->toArray());

            Event::dispatch(new AssistantCreated($clone));

            return $this->repository->loadRelations($clone, ['userPrompts', 'aiTools', 'tags']);
        });
    }

    private function handleUserPrompts(Assistant $assistant, ?array $prompts, bool $replace = false): void
    {
        if ($prompts === null) {
            return;
        }

        if ($replace) {
            $this->repository->replaceUserPrompts($assistant, $prompts);
        } else {
            $assistant->userPrompts()->createMany($prompts);
        }
    }

    private function handleAiTools(Assistant $assistant, ?array $tools): void
    {
        if ($tools === null) {
            return;
        }

        $toolIds = collect($tools)->pluck('id')->toArray();
        $this->repository->syncTools($assistant, $toolIds);
    }

    private function handleTags(Assistant $assistant, ?array $tags): void
    {
        if ($tags === null) {
            return;
        }

        $existing = $this->tagRepository->findIdsByTexts($tags);
        $newTexts = collect($tags)->diff(array_keys($existing));

        if ($newTexts->isNotEmpty()) {
            $this->tagRepository->insertMany($newTexts->map(fn (string $text) => [
                'text' => $text,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray());
            $existing = $this->tagRepository->findIdsByTexts($tags);
        }

        $this->repository->syncTags($assistant, array_values($existing));
    }
}
