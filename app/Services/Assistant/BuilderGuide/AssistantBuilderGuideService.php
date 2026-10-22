<?php

declare(strict_types=1);

namespace App\Services\Assistant\BuilderGuide;

use App\Models\Ai\AiModel;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantCategory;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantTag;
use App\Services\Ai\Agents\Utils\ExtractTextCollector;
use App\Services\Ai\AiService;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\SystemModels\Values\WellKnownSystemModelTypes;
use App\Services\Assistant\BuilderGuide\Values\AssistantBuilderDraftSchema;
use App\Services\Assistant\Values\AssistantFieldRules;
use App\Services\Assistant\Values\AssistantSystemModelTypes;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Runs one turn of the assistant builder's guide chat: the creator talks to an
 * LLM that asks about the assistant they want, answers questions about the
 * builder, and fills builder fields through structured output. The frontend
 * applies the returned `updates` to the (possibly unsaved) draft, which is why
 * the draft is sent along instead of read from the database.
 *
 * The run uses the `assistant_guide` system model (falling back to the default
 * chat model), not the draft's model: the draft may have no model yet, and the
 * guide must work before one is picked.
 */
class AssistantBuilderGuideService
{
    /**
     * Per-file cap on the knowledge excerpts handed to the guide.
     */
    private const int FILE_EXCERPT_CHARS = 4000;

    /**
     * Cap on all knowledge excerpts together.
     */
    private const int FILES_TOTAL_CHARS = 16000;

    public function __construct(
        private readonly AssistantBuilderGuideAgentFactory $agentFactory,
        private readonly AiService $aiService,
        private readonly AiModelRepository $modelRepository,
        private readonly FileStorageService $fileStorage,
        private readonly ExtractTextCollector $extractTextCollector,
    ) {
    }

    /**
     * One guide turn: builds the agent, sends it, and normalizes the
     * structured output into the shape the builder applies.
     *
     * @param array<string, mixed>                       $draft             the current builder draft, keyed by {@see AssistantBuilderDraftSchema::FIELDS}
     * @param list<array{role: string, content: string}> $messages          the guide conversation; the last one is the creator's
     * @param list<string>                               $avatarBackgrounds ids of the avatar background presets the builder offers (they live in the frontend); none leaves the avatar to the creator
     *
     * @return array{reply: string, updates: array<string, array<string, string>|list<string>|string>}
     */
    public function respond(
        Assistant $assistant,
        array $draft,
        array $messages,
        string $locale,
        array $avatarBackgrounds = [],
    ): array {
        $categories = AssistantCategory::query()->orderBy('text')->pluck('text', 'id')->all();
        $models = $this->modelRepository->findAll()
            ->filter(static fn (AiModel $model): bool => $model->active)
            ->mapWithKeys(static fn (AiModel $model): array => [$model->model_id => $model->label])
            ->all();
        $settingOptions = $this->loadSettingOptions();

        $agent = $this->agentFactory->createAgent([
            'model' => $this->resolveModel($assistant),
            'instructions' => $this->buildInstructions($assistant, $draft, $locale, $categories, $models, $settingOptions, $avatarBackgrounds),
            'messages' => $this->toMessages($messages),
            'categoryIds' => array_map(strval(...), array_keys($categories)),
            'modelIds' => array_map(strval(...), array_keys($models)),
            'settingOptions' => $settingOptions,
            'avatarBackgrounds' => $avatarBackgrounds,
        ]);

        $response = $agent->send();

        if (!$response instanceof StructuredAgentResponse) {
            throw new \UnexpectedValueException('The builder guide did not return structured output.');
        }

        $structured = $response->toArray();

        return [
            'reply' => trim((string)($structured['reply'] ?? '')),
            'updates' => $this->normalizeUpdates($structured['updates'] ?? [], $assistant, $categories, $models, $settingOptions, $avatarBackgrounds),
        ];
    }

    /**
     * The values the creator may pick for each settings field (the builder's
     * select options, minus "not set"). A setting without options is left
     * out, so the guide is never offered it.
     *
     * @return array<string, list<string>>
     */
    private function loadSettingOptions(): array
    {
        $settings = AssistantSetting::query()
            ->whereIn('key', array_values(AssistantBuilderDraftSchema::SETTING_FIELDS))
            ->get()
            ->keyBy('key');

        $out = [];

        foreach (AssistantBuilderDraftSchema::SETTING_FIELDS as $field => $key) {
            $values = array_map(
                static fn (mixed $option): string => \is_array($option) ? (string) ($option['value'] ?? '') : '',
                $settings->get($key)->ui_options ?? [],
            );
            $values = array_values(array_filter($values, static fn (string $value): bool => '' !== $value));

            if ([] !== $values) {
                $out[$field] = $values;
            }
        }

        return $out;
    }

    private function resolveModel(Assistant $assistant): AiModel
    {
        foreach ([AssistantSystemModelTypes::GUIDE, WellKnownSystemModelTypes::DEFAULT] as $type) {
            $systemModel = $this->aiService
                ->getSystemModels()
                ->findAllFiltered(modelType: $type)
                ->first();

            if (null !== $systemModel?->model && $systemModel->model->active) {
                return $systemModel->model;
            }
        }

        abort_if('' === (string) $assistant->model, 422, 'No model available.');

        return $this->modelRepository->findOneOrFail($assistant->model);
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     *
     * @return list<Message>
     */
    private function toMessages(array $messages): array
    {
        return array_map(
            static fn (array $message): Message => 'assistant' === $message['role']
                ? new AssistantMessage($message['content'])
                : new UserMessage($message['content']),
            $messages,
        );
    }

    /**
     * @param array<string, mixed>        $draft
     * @param array<int|string, string>   $categories        category text by id
     * @param array<string, string>       $models            model label by model_id
     * @param array<string, list<string>> $settingOptions    option values per settings field
     * @param list<string>                $avatarBackgrounds
     */
    private function buildInstructions(Assistant $assistant, array $draft, string $locale, array $categories, array $models, array $settingOptions, array $avatarBackgrounds): string
    {
        $current = [];

        foreach (AssistantBuilderDraftSchema::FIELDS as $field) {
            $current[$field] = $draft[$field] ?? null;
        }

        $jsonFlags = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
        $currentJson = json_encode($current, $jsonFlags);
        $files = $this->collectKnowledge($assistant);
        $required = implode(', ', AssistantBuilderDraftSchema::REQUIRED_FIELDS);
        $missing = array_values(array_filter(
            AssistantBuilderDraftSchema::REQUIRED_FIELDS,
            static fn (string $field): bool => null === ($current[$field] ?? null) || '' === $current[$field],
        ));
        $missingText = [] === $missing ? '(none)' : implode(', ', $missing);
        $categoryList = $this->formatOptions($categories);
        $modelList = $this->formatOptions($models);
        $settingList = $this->formatOptions(array_map(
            static fn (array $values): string => implode(', ', $values),
            $settingOptions,
        ));
        $avatarRule = [] === $avatarBackgrounds
            ? ''
            : "\n- `avatar` is the emoji and background shown on the assistant's card and detail page. Pick one single emoji that clearly symbolises the assistant's purpose and one of these background ids that suits it: " . implode(', ', $avatarBackgrounds) . '. Set it together with the first version of the assistant, or when the creator asks for a new look.';
        $avatarNote = [] === $avatarBackgrounds ? 'the avatar, ' : '';

        return <<<PROMPT
            You are the setup guide of HAWKI's assistant builder. HAWKI is the AI chat platform of a university; its members build custom AI assistants (tutors, writing helpers, research aids, admin helpers, ...) that they and others can chat with.

            Your job is to help the creator set up their assistant through a friendly conversation:
            - Find out, one or two questions at a time, what the assistant is for, who will use it, what it should and should not do, and in which tone it should answer.
            - As soon as you know enough, fill the builder fields yourself through `updates`. Prefer a solid first version over asking many questions; refine it as you learn more.
            - When the creator asks you to build, create, set up or fill in the assistant for them, do it in this same turn: fill every required field that is still empty (and greeting and starter prompts), making sensible assumptions instead of asking first. Then briefly say what you assumed and offer to adjust it.
            - Answer questions about the builder and about writing good assistant instructions.
            - Keep replies short and conversational. Do not paste the full system prompt into your reply; briefly say which fields you filled or changed instead.
            - When the creator's latest message only reports newly uploaded knowledge files, react to them on your own: say in one sentence what they contain, based on their excerpts below. If the builder is still mostly empty, shape a first version of the assistant around them right away; otherwise adjust the instructions (and description, starter prompts, ...) so the assistant makes use of them. End with one focused question or suggestion.

            How to fill `updates`:
            - Set a field only when you want to change it; use null for every field that should stay as it is. Never clear a field the creator filled unless they ask.
            - `systemPrompt` is the assistant's full instructions, written to the assistant in second person ("You are ..."): role, audience, tasks, boundaries, tone and output format. Always send the complete prompt, not a diff.
            - `starterPrompts` are short example questions a user might open the chat with, written from the user's perspective.
            - `name` is short (a few words). `description` is one sentence for the assistant card. `greeting` is the assistant's first message to its users.
            - `handle` is the unique @handle users mention the assistant with: lowercase letters, digits and hyphens only, derived from the name (e.g. `statistics-tutor`). It is made unique automatically.
            - `categoryId` is the id of the category below that fits best.
            - `model` is the model_id of one of the available models below. Unless the creator wants a specific one, pick a capable general-purpose model.
            - `language`, `formality` and `answerStyle` set the language the assistant answers in, its tone and its answer length; each takes one of the values listed below. Set them when the creator states a preference or it clearly follows from the audience.
            - `tags` are short, general keywords describing the assistant. Always send the complete list, not only the additions.
            - Write field contents in the language the assistant's users will speak; if unclear, use the creator's language.{$avatarRule}

            Required fields: {$required}. Still empty: {$missingText}.

            Other builder settings you cannot change, but can explain: {$avatarNote}the model parameters (temperature, top-p, max tokens), tools, knowledge files and the release (private, shared, organisation-wide, ...). Knowledge files can also be added right here in this chat, with the paperclip button or by dropping them on the chat; they become part of the assistant's knowledge.

            Reply in the language the creator writes in. The creator's interface language is `{$locale}`.

            Current builder fields:
            {$currentJson}

            Categories (id: name):
            {$categoryList}

            Available models (model_id: name):
            {$modelList}

            Settings (field: allowed values):
            {$settingList}

            Knowledge files attached to the assistant:
            {$files}
            PROMPT;
    }

    /**
     * @param array<int|string, string> $options
     */
    private function formatOptions(array $options): string
    {
        if ([] === $options) {
            return '(none)';
        }

        return implode("\n", array_map(
            static fn (int|string $key, string $label): string => "- {$key}: {$label}",
            array_keys($options),
            $options,
        ));
    }

    /**
     * Name and text excerpt of every knowledge file, so the guide can shape
     * the instructions around them.
     */
    private function collectKnowledge(Assistant $assistant): string
    {
        $assistant->loadMissing('assistantAttachments');

        if ($assistant->assistantAttachments->isEmpty()) {
            return '(none)';
        }

        $blocks = [];
        $budget = self::FILES_TOTAL_CHARS;

        foreach ($assistant->assistantAttachments as $attachment) {
            $excerpt = '';

            if (0 < $budget) {
                $file = $this->fileStorage->retrieve(StoredFileIdentifier::fromAssistantAttachment($attachment));
                $content = null === $file ? '' : $this->extractTextCollector->collect($file);
                $excerpt = mb_substr($content, 0, min(self::FILE_EXCERPT_CHARS, $budget));
                $budget -= mb_strlen($excerpt);

                if (mb_strlen($excerpt) < mb_strlen($content)) {
                    $excerpt .= ' [...]';
                }
            }

            $blocks[] = '' === $excerpt
                ? "- {$attachment->name}"
                : "- {$attachment->name}:\n\"\"\"\n{$excerpt}\n\"\"\"";
        }

        return implode("\n", $blocks);
    }

    /**
     * Keep only well-formed, non-empty field values. Category, model and the
     * settings are re-checked against the options even though the schema enumerates them:
     * not every provider enforces structured output strictly.
     *
     * @param array<int|string, string>   $categories
     * @param array<string, string>       $models
     * @param array<string, list<string>> $settingOptions
     * @param list<string>                $avatarBackgrounds
     *
     * @return array<string, array<string, string>|list<string>|string>
     */
    private function normalizeUpdates(mixed $updates, Assistant $assistant, array $categories, array $models, array $settingOptions, array $avatarBackgrounds): array
    {
        if (!\is_array($updates)) {
            return [];
        }

        $out = [];

        $handle = \is_string($updates['handle'] ?? null) ? $this->uniqueHandle($updates['handle'], $assistant) : null;

        if (null !== $handle) {
            $out['handle'] = $handle;
        }

        $categoryId = $updates['categoryId'] ?? null;

        if ((\is_string($categoryId) || \is_int($categoryId)) && \array_key_exists($categoryId, $categories)) {
            $out['categoryId'] = (string) $categoryId;
        }

        $model = $updates['model'] ?? null;

        if (\is_string($model) && \array_key_exists($model, $models)) {
            $out['model'] = $model;
        }

        foreach ($settingOptions as $field => $values) {
            $value = $updates[$field] ?? null;

            if (\is_string($value) && \in_array($value, $values, true)) {
                $out[$field] = $value;
            }
        }

        if (\is_array($updates['tags'] ?? null)) {
            $out['tags'] = $this->normalizeTags($updates['tags']);
        }

        $avatar = \is_array($updates['avatar'] ?? null) ? $this->normalizeAvatar($updates['avatar'], $avatarBackgrounds) : [];

        if ([] !== $avatar) {
            $out['avatar'] = $avatar;
        }

        foreach (AssistantBuilderDraftSchema::TEXT_FIELDS as $field) {
            $value = $updates[$field] ?? null;

            if ('starterPrompts' === $field) {
                if (!\is_array($value)) {
                    continue;
                }

                $prompts = array_values(array_unique(array_filter(
                    array_map(static fn ($p): string => \is_string($p) ? trim($p) : '', $value),
                    static fn (string $p): bool => '' !== $p,
                )));
                $out[$field] = $prompts;

                continue;
            }

            if (!\is_string($value) || '' === trim($value)) {
                continue;
            }

            $out[$field] = 'name' === $field
                ? mb_substr(trim($value), 0, AssistantFieldRules::NAME_MAX_LENGTH)
                : trim($value);
        }

        return $out;
    }

    /**
     * The proposed avatar's usable parts: an emoji that is one grapheme and not
     * plain text, and a background among the offered presets. Either may be
     * missing; the frontend keeps the current value for that part.
     *
     * @param array<mixed> $proposal
     * @param list<string> $backgrounds
     *
     * @return array{emoji?: string, background?: string}
     */
    private function normalizeAvatar(array $proposal, array $backgrounds): array
    {
        $out = [];

        $emoji = \is_string($proposal['emoji'] ?? null) ? trim($proposal['emoji']) : '';

        if (1 === preg_match('/^\X$/u', $emoji) && 0 === preg_match('/^[\p{L}\p{N}\p{P}\p{Z}]/u', $emoji)) {
            $out['emoji'] = $emoji;
        }

        $background = $proposal['background'] ?? null;

        if (\is_string($background) && \in_array($background, $backgrounds, true)) {
            $out['background'] = $background;
        }

        return $out;
    }

    /**
     * Trimmed, de-duplicated tag names. A name matching an existing tag up
     * to case is replaced by that tag's spelling: the frontend reuses tags
     * by exact name, and creating "statistics" next to "Statistics" would
     * fail the case-insensitive unique check. Only the proposed names are
     * looked up, so this is one small query however many tags exist.
     *
     * @param list<mixed> $proposal
     *
     * @return list<string>
     */
    private function normalizeTags(array $proposal): array
    {
        $names = [];

        foreach ($proposal as $name) {
            $name = \is_string($name) ? trim($name) : '';

            if ('' === $name) {
                continue;
            }

            $names[mb_strtolower($name)] ??= $name;
        }

        if ([] === $names) {
            return [];
        }

        $existing = AssistantTag::query()
            ->whereIn(DB::raw('LOWER(text)'), array_map(strval(...), array_keys($names)))
            ->pluck('text')
            ->mapWithKeys(static fn (string $text): array => [mb_strtolower($text) => $text])
            ->all();

        $out = [];

        foreach ($names as $key => $name) {
            $out[] = $existing[$key] ?? $name;
        }

        return $out;
    }

    /**
     * Turn the guide's proposal into a valid handle (see
     * {@see AssistantFieldRules}: `[a-zA-Z0-9_-]`, at most 255 characters,
     * unique among assistants and model labels), appending `-2`, `-3`, ...
     * when it is taken. The stem is shortened per suffix so stem plus suffix
     * always fits the `assistants.handle` column, whatever the proposal's
     * length.
     */
    private function uniqueHandle(string $proposal, Assistant $assistant): ?string
    {
        $base = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', Str::lower(Str::ascii($proposal))), '-_');

        if ('' === $base) {
            return null;
        }

        $candidate = rtrim(mb_substr($base, 0, AssistantFieldRules::HANDLE_MAX_LENGTH), '-_');

        for ($suffix = 2; $this->handleTaken($candidate, $assistant); ++$suffix) {
            $stem = rtrim(
                mb_substr($base, 0, AssistantFieldRules::HANDLE_MAX_LENGTH - mb_strlen((string) $suffix) - 1),
                '-_',
            );
            $candidate = "{$stem}-{$suffix}";
        }

        return $candidate;
    }

    private function handleTaken(string $handle, Assistant $assistant): bool
    {
        return Assistant::query()->where('handle', $handle)->whereKeyNot($assistant->getKey())->exists()
            || AiModel::query()->where('label', $handle)->exists();
    }
}
