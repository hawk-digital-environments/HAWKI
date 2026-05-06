<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Events\AssistantCreated;
use App\Events\AssistantUpdated;
use App\Events\AssistantTriggerReleaseStatus;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\Category;
use App\Models\Assistants\Language;
use App\Models\User;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Repositories\CategoryRepository;
use App\Services\Assistant\Repositories\LanguageRepository;
use App\Services\Assistant\Repositories\OrganizationRepository;
use App\Services\Assistant\Repositories\TagRepository;
use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        private CategoryRepository $categoryRepository,
        private LanguageRepository $languageRepository,
        private OrganizationRepository $organizationRepository,
        private DatabaseManager $db,
    ) {}

    public function list(?User $user = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->all($user, $perPage);
    }

    public function find(Assistant $assistant): Assistant
    {
        return $assistant;
    }

    public function create(array $data, User $creator): Assistant
    {
        return $this->db->transaction(function () use ($data, $creator) {
            $category = $this->resolveCategory($data['category'] ?? null);
            $language = $this->resolveLanguage($data['language'] ?? null);

            $assistantData = collect($data)
                ->except(['user_prompts', 'ai_tools', 'tags', 'category', 'language'])
                ->merge([
                    'creator_id' => $creator->id,
                    'remixed_creator_id' => null,
                    'category_id' => $category?->id,
                    'language_id' => $language?->id,
                    'organization_id' => $this->organizationRepository->getForUser($creator)?->id,
                ])
                ->toArray();

            $assistant = $this->repository->create($assistantData);

            $this->handleUserPrompts($assistant, $data['user_prompts'] ?? null);
            $this->handleAiTools($assistant, $data['ai_tools'] ?? null);
            $this->handleTags($assistant, $data['tags'] ?? null);

            Event::dispatch(new AssistantCreated($assistant));

            return $this->repository->loadRelations($assistant, ['user_prompts', 'ai_tools', 'tags']);
        });
    }

    public function update(Assistant $assistant, array $data): Assistant
    {
        return $this->db->transaction(function () use ($assistant, $data) {
            $versionText = $data['version_text'] ?? null;

            $assistantFields = collect($data)
                ->except(['user_prompts', 'ai_tools', 'tags', 'version_text', 'category', 'language'])
                ->toArray();

            if (isset($data['category'])) {
                $category = $this->resolveCategory($data['category']);
                $assistantFields['category_id'] = $category?->id;
            }

            if (isset($data['language'])) {
                $language = $this->resolveLanguage($data['language']);
                $assistantFields['language_id'] = $language?->id;
            }

            $changedKeys = array_values(array_filter(
                array_keys($this->repository->update($assistant, $assistantFields)),
                fn (string $key) => $key !== 'updated_at',
            ));

            if ($this->handleUserPrompts($assistant, $data['user_prompts'] ?? null, true)) {
                $changedKeys[] = 'user_prompts';
            }

            if ($this->handleAiTools($assistant, $data['ai_tools'] ?? null)) {
                $changedKeys[] = 'ai_tools';
            }

            if ($this->handleTags($assistant, $data['tags'] ?? null)) {
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

    private function resolveCategory(?string $categoryText): ?Category
    {
        if ($categoryText === null) {
            return null;
        }

        return $this->categoryRepository->findOrCreateByText($categoryText);
    }

    private function resolveLanguage(?string $languageText): ?Language
    {
        if ($languageText === null) {
            return null;
        }

        return $this->languageRepository->findOrCreateByText($languageText);
    }

    private function handleUserPrompts(Assistant $assistant, ?array $prompts, bool $replace = false): bool
    {
        if ($prompts === null) {
            return false;
        }

        if ($replace) {
            return $this->repository->replaceUserPrompts($assistant, $prompts);
        }

        $assistant->user_prompts()->createMany($prompts);

        return true;
    }

    private function handleAiTools(Assistant $assistant, ?array $tools): bool
    {
        if ($tools === null) {
            return false;
        }

        $toolIds = collect($tools)->pluck('id')->toArray();
        $result = $this->repository->syncTools($assistant, $toolIds);

        return $result['attached'] !== [] || $result['detached'] !== [] || $result['updated'] !== [];
    }

    private function handleTags(Assistant $assistant, ?array $tags): bool
    {
        if ($tags === null) {
            return false;
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

        $result = $this->repository->syncTags($assistant, array_values($existing));

        return $result['attached'] !== [] || $result['detached'] !== [] || $result['updated'] !== [];
    }
}
