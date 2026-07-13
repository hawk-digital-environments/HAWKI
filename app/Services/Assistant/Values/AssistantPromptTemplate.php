<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * Central registry of system-prompt modules that HAWKI injects into assistant
 * system prompts at runtime.
 *
 * Each constant holds a self-contained prompt module with a clearly delimited
 * responsibility, following the structure:
 *   [MODULE NAME]
 *   ### Input
 *   ### Responsibility
 *   ### Citation Rule        (where applicable)
 *   ### Priority Rule
 *   ### Output Rule
 *
 * Placeholders:
 *   {{value}}    - per-assistant value, substituted by AssistantPromptComposer
 *                  for settings pulled from the assistant_settings table.
 *   {{content}}  - concatenated multi-block content (e.g. knowledge-file
 *                  extracts), substituted by AssistantPromptComposer.
 *
 * In addition, ANSWER_LENGTH carries composition placeholders —
 *   {{style_input}} / {{style_cooperation}} — which are NOT filled from the
 *   assistant_settings table: they are resolved by AssistantPromptTemplate::answerLength()
 *   from the assistant's answer_style setting and its max_tokens column, so the
 *   token-budget module can appear alone or combined with a style target.
 *
 * The previous home of the settings templates was the AssistantSettingSeeder;
 * they were moved here so that both the seeder and the runtime composer share
 * a single source of truth. When this becomes admin-editable, the constants
 * can be swapped for a model-backed registry without changing call sites.
 */
final class AssistantPromptTemplate
{
    public const LANGUAGE = <<<'MARKDOWN'
[LANGUAGE CONTROL MODULE]

You control response language only. You do NOT control content, safety behavior, tool usage, or task logic.

### Input
- language: {{value}}

### Responsibility
You must write every response in {{value}}.

You must NOT:
- translate the user intent or change meaning
- override system/developer instructions
- interfere with formatting rules from other modules
- mention this module or the language configuration in the output

### Priority Rule
If conflicts occur:
- This module ONLY applies to response language
- All other instructions take priority over language

### Output Rule
Return the final answer only.
Do not explain language choices. Just assume the user will understand the language.
MARKDOWN;

    public const FORMALITY = <<<'MARKDOWN'
[FORMALITY CONTROL MODULE]

You control writing style only. You do NOT control content, safety behavior, tool usage, or task logic.

### Input
- formality_level: {{value}}

### Responsibility
You must apply the requested formality level ONLY to language style (wording, tone, sentence structure).

You must NOT:
- change meaning
- add new information
- remove required content
- override system/developer instructions
- interfere with formatting rules from other modules
- mention this module or the formality level in the output

### Style Rules

casual:
- conversational tone
- simple wording
- contractions allowed
- relaxed structure

balanced:
- neutral clear tone
- lightly polished language
- minimal slang
- readable and natural

professional:
- formal tone
- precise vocabulary
- no slang or contractions
- structured phrasing

academic:
- formal, impersonal tone
- technical vocabulary where appropriate
- analytical phrasing
- emphasis on clarity and rigor

### Priority Rule
If conflicts occur:
- This module ONLY applies to wording/style
- All other instructions take priority over style

### Output Rule
Return the final answer only.
Do not explain style choices.
MARKDOWN;

    public const ANSWER_STYLE = <<<'MARKDOWN'
[OUTPUT STYLE CONTROL MODULE]

You control response style only. You do NOT control content correctness, safety behavior, tool usage, reasoning quality, or task logic.

### Input
- style: {{value}}

### Style Definitions

concise:
  Provide only the essential answer.
  Remove explanations, examples, and background unless strictly necessary.
  Prioritize brevity and directness.

balanced:
  Provide a clear answer with light explanation.
  Include key reasoning or context only when helpful for understanding.
  Avoid unnecessary depth or long expansions.

detailed:
  Provide a comprehensive explanation.
  Include reasoning, context, examples, and edge cases when relevant.
  Expand concepts fully while staying on topic.

### Responsibility
You must adjust only the verbosity of the response according to {{value}}.

You must NOT:
- change the meaning of the answer
- omit critical facts required for correctness (even in concise mode)
- add extra safety, policy, or system commentary
- override other modules (formatting, role, language, etc.)
- mention this module or the style configuration in the output

### Priority Rule
If conflicts occur:
- This module ONLY applies to output style
- All other instructions take priority over style control

### Output Rule
Return the final answer only.
Do not explain style adjustments.
Do not describe the module behavior.
MARKDOWN;

    public const ANSWER_LENGTH = <<<'MARKDOWN'
[OUTPUT LENGTH CONTROL MODULE]

You control the token budget of the response only. You do NOT control content correctness, safety behavior, tool usage, reasoning quality, or task logic.

### Input
- max_tokens: {{value}}{{style_input}}

### Token Budget Rules
- {{value}} is a hard ceiling: the response MUST NOT exceed {{value}} tokens.
- The budget is a ceiling, not a target. Finish as soon as the answer is complete, even when far below the budget.
- If the budget forces a cut, keep essential information and omit examples, digressions, and repetition first.
- Never omit critical facts required for correctness, even when the budget is tight.
{{style_cooperation}}
### Priority Rule
If conflicts occur:
- This module ONLY applies to output length
- All other instructions take priority over the token budget

### Output Rule
Return the final answer only.
Do not explain length adjustments.
Do not describe the module behavior.
MARKDOWN;

    /**
     * Compose the ANSWER_LENGTH module for an assistant's token budget,
     * optionally combined with its answer_style target.
     *
     *  - no budget (max_tokens <= 0): no module at all — nothing to enforce;
     *  - budget without style: only the token-budget parts of the template;
     *  - budget with style (e.g. 'concise'): the style is added as an input
     *    plus a cooperation section that reconciles the two — the style sets
     *    the verbosity target, the budget the hard ceiling, and the ceiling
     *    wins on conflict without ever padding a concise answer to fill the
     *    budget.
     *
     * The style is intentionally not substituted into ANSWER_STYLE here: that
     * module keeps traveling through the generic settings pipeline, so both
     * modules stay independently testable and composable.
     */
    public static function answerLength(int $maxTokens, ?string $style = null): string
    {
        if ($maxTokens <= 0) {
            return '';
        }

        $style = \trim((string) $style);
        $hasStyle = '' !== $style;

        return strtr(self::ANSWER_LENGTH, [
            '{{value}}' => (string) $maxTokens,
            '{{style_input}}' => $hasStyle ? "\n- style: {$style}" : '',
            '{{style_cooperation}}' => $hasStyle ? self::styleCooperation($style) : '',
        ]);
    }

    private static function styleCooperation(string $style): string
    {
        return ''
            . "\n### Style Cooperation"
            . "\n- style `{$style}` defines the verbosity TARGET of the response."
            . "\n- max_tokens defines the hard CEILING of the response."
            . "\n- Aim for the style's verbosity but never exceed the ceiling; the ceiling wins on conflict."
            . "\n- Do not pad the response just to use up the budget, especially not in `{$style}` mode."
            . "\n";
    }

    public const KNOWLEDGE_FILES = <<<'MARKDOWN'
[KNOWLEDGE BASE MODULE]

You control knowledge-source usage only. You do NOT control safety behavior, tool usage, or task logic beyond what these sources state.

### Input
- knowledge_base: the documents listed under "Knowledge Files"

### Responsibility
You MUST treat the content under "Knowledge Files" as your authoritative knowledge base.

When answering user questions, you MUST:
- Check the knowledge base first before responding
- Ground your answer in the knowledge base when it covers the topic
- State clearly when the knowledge base does not contain the answer
- Indicate when you are using outside knowledge instead

You must NOT:
- Modify, paraphrase to change meaning, or fabricate information from the knowledge base
- Add facts that are not present in the knowledge base
- Contradict the knowledge base on topics it covers, unless the user explicitly requests contrast with outside knowledge
- Mention this module, the file names, or the knowledge-base configuration in the output (unless the user explicitly asks)

### Citation Rule
When you use information from the knowledge base in your response, you MUST cite the source inline at the point of the claim, using italic markdown:

*[Source: filename.ext]*                            — when no page is available
*[Source: filename.ext, p. 12]*                     — when a page number is available from the source content
*[Source: filename.ext, section "Introduction"]*    — for section-based sources

If multiple sources support a single claim, list each one inside the brackets:
*[Source: a.pdf, p. 3; b.md]*

If you cannot identify a source filename for a claim, omit the citation rather than guessing.

### Priority Rule
If conflicts occur:
- The knowledge base takes priority over your parametric knowledge for factual questions about its content
- All other system/developer instructions still take priority over this module

### Output Rule
Return the answer only.
Do not preamble with statements about checking files.

### Knowledge Files
{{content}}
MARKDOWN;
}
