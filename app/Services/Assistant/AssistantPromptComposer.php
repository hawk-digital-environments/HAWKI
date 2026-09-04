<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use App\Models\User;
use App\Services\Ai\Agents\Utils\ExtractTextCollector;
use App\Services\Assistant\Values\AssistantPromptTemplate;
use App\Services\Assistant\Values\AssistantReleaseStage;
use App\Services\Assistant\Values\WellKnownAssistantSettingKeys;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Collection;

class AssistantPromptComposer
{
    public function __construct(
        private readonly FileStorageService   $fileStorage,
        private readonly Gate                 $gate,
        private readonly ExtractTextCollector $extractTextCollector,
    ) {
    }

    public function compose(Assistant $assistant, ?User $actor = null): string
    {
        $prompt = $assistant->system_prompt ?? '';

        $values = $assistant->settingValues()->with('setting')->get();

        $effectiveValues = $this->resolveEffectiveValues($values);

        $answerStyle = null;

        foreach ($effectiveValues as $entry) {
            if (WellKnownAssistantSettingKeys::ANSWER_STYLE === $entry['setting']->key
                && \is_string($entry['value'])) {
                $answerStyle = $entry['value'];
            }

            $fragment = $this->resolveFragment($entry['setting'], $entry['value']);

            if ('' !== $fragment) {
                $prompt .= "\n\n" . $fragment;
            }
        }

        // The token-budget module is composed in code rather than read from a
        // settings row: max_tokens is a plain assistant column, and the module
        // must cooperate with the answer_style target captured above. Appended
        // before the knowledge-files fragment, which stays last (see below).
        $lengthFragment = AssistantPromptTemplate::answerLength(
            (int) ($assistant->max_tokens ?? 0),
            $answerStyle,
        );

        if ('' !== $lengthFragment) {
            $prompt .= "\n\n" . $lengthFragment;
        }

        $attachmentFragment = $this->resolveAttachmentFragment($assistant, $actor);

        if ('' !== $attachmentFragment) {
            $prompt .= "\n\n" . $attachmentFragment;
        }

        return $prompt;
    }

    /**
     * Builds the knowledge-files fragment appended at the end of the system
     * prompt. For every attachment on the assistant, the stored file is
     * retrieved and each of its extracts (plain-text virtual extracts or
     * converter-produced text) is concatenated as a "Source: {filename}"
     * block and substituted into the {{content}} placeholder of the
     * AssistantPromptTemplate::KNOWLEDGE_FILES module.
     *
     * Knowledge files are injected for every actor who may run the
     * assistant (the `view` gate): an assistant's knowledge is part of its
     * behaviour, so anyone who can address it experiences the files'
     * effect. The raw file list and downloads stay protected — the
     * `?include=attachments` JSON:API path remains behind the stricter
     * viewAttachments gate (creator or org admin).
     *
     * Without an actor (rare, e.g. an unresolvable user during the group
     * chat shutdown flow) the fragment is only injected for publicly
     * visible assistants, matching the visibility anyone would have.
     *
     * Files whose extracts are null (converter not enabled at storage time)
     * or empty (e.g. images with no extractable text) are silently skipped.
     * Files whose underlying blob is missing on disk are also skipped so a
     * stale attachment row never breaks prompt composition.
     *
     * Placement at the end of the prompt preserves the cached prefix
     * (system_prompt + settings) when files are added, removed or replaced.
     */
    private function resolveAttachmentFragment(Assistant $assistant, ?User $actor): string
    {
        if (!$this->actorMayUseKnowledge($assistant, $actor)) {
            return '';
        }

        if ($assistant->attachments->isEmpty()) {
            return '';
        }

        $blocks = [];

        foreach ($assistant->attachments as $attachment) {
            $file = $this->fileStorage->retrieve(StoredFileIdentifier::fromAttachment($attachment));

            if (null === $file) {
                continue;
            }

            // Only PLAIN_TEXT extracts are inlined into the text system prompt;
            // image/binary extracts carry no prompt text and would break the
            // provider's JSON encoding. Sanitized inside the collector.
            $content = $this->extractTextCollector->collect($file);

            if ('' === $content) {
                continue;
            }

            $blocks[] = "Source: {$attachment->name}\n{$content}";
        }

        if ([] === $blocks) {
            return '';
        }

        return str_replace(
            '{{content}}',
            implode("\n\n", $blocks),
            AssistantPromptTemplate::KNOWLEDGE_FILES,
        );
    }

    /**
     * Whether the actor may run the assistant and therefore have its
     * knowledge files injected: viewers pass the `view` gate; a missing
     * actor only passes for publicly visible assistants.
     */
    private function actorMayUseKnowledge(Assistant $assistant, ?User $actor): bool
    {
        if (null === $actor) {
            return \in_array($assistant->release_stage, AssistantReleaseStage::publiclyVisibleCases(), true);
        }

        return $this->gate->forUser($actor)->check('view', $assistant);
    }

    /**
     * @param Collection<int, AssistantSettingValue> $values
     *
     * @return array<int, array{setting: AssistantSetting, value: mixed}>
     */
    private function resolveEffectiveValues($values): array
    {
        $result = [];

        foreach ($values as $valueRecord) {
            $setting = $valueRecord->setting;

            if (null === $setting) {
                continue;
            }

            $value = $valueRecord->value ?? $setting->default_value;

            if (null === $value || [] === $value) {
                continue;
            }

            // An empty string is the catalog's "not set" option: the user
            // explicitly chose no behavior, so no module is appended (an
            // earlier version injected the template with a blank {{value}}).
            if (\is_string($value) && '' === \trim($value)) {
                continue;
            }

            $result[] = ['setting' => $setting, 'value' => $value];
        }

        return $result;
    }

    private function resolveFragment(AssistantSetting $setting, mixed $value): string
    {
        if ('select' === $setting->ui_type) {
            foreach ($setting->ui_options ?? [] as $option) {
                if ($option['value'] === $value) {
                    if (!empty($option['prompt'])) {
                        return $option['prompt'];
                    }

                    break;
                }
            }
        }

        if (null !== $setting->prompt_template) {
            $displayValue = \is_array($value) ? implode(', ', $value) : (string) $value;

            return str_replace('{{value}}', $displayValue, $setting->prompt_template);
        }

        return '';
    }
}
