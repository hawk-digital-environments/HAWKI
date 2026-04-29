<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Events\AssistantCreated;
use App\Events\AssistantUpdated;
use App\Models\Assistants\Assistant;
use App\Services\Assistant\Repositories\AssistantRepository;
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
                'original_creator_id' => $creatorId,
            ]));

            $this->handleUserPrompts($assistant, $data['user_prompts'] ?? null);
            $this->handleAiTools($assistant, $data['ai_tools'] ?? null);

            Event::dispatch(new AssistantCreated($assistant));

            return $this->repository->loadRelations($assistant, ['userPrompts', 'aiTools']);
        });
    }

    public function update(Assistant $assistant, array $data): Assistant
    {
        return $this->db->transaction(function () use ($assistant, $data) {
            $versionText = $data['version_text'] ?? null;

            $scalarData = collect($data)
                ->except(['user_prompts', 'ai_tools', 'version_text'])
                ->toArray();

            $this->repository->update($assistant, $scalarData);

            $this->handleUserPrompts($assistant, $data['user_prompts'] ?? null, true);
            $this->handleAiTools($assistant, $data['ai_tools'] ?? null);

            Event::dispatch(new AssistantUpdated($assistant, $versionText));

            return $this->repository->loadRelations($assistant, ['userPrompts', 'aiTools']);
        });
    }

    public function delete(Assistant $assistant): void
    {
        $this->repository->delete($assistant);
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
}
