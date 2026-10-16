<?php

declare(strict_types=1);

namespace App\Services\Assistant\BuilderGuide\Values;

use App\Services\Assistant\Values\WellKnownAssistantSettingKeys;

/**
 * The builder draft's schema: every field the builder and its guide may
 * read and fill, the constraints on each, and the validation rules the API
 * applies to a submitted draft. One source of truth shared by the guide
 * service (field catalog, instructions, normalization), the guide agent's
 * structured-output schema, and the request validation — the field lists
 * must never drift apart.
 */
final class AssistantBuilderDraftSchema
{
    public const int MAX_STARTER_PROMPTS = 6;
    public const int MAX_TAGS = 5;

    /**
     * Longest tag the guide may propose.
     */
    public const int MAX_TAG_CHARS = 40;

    /**
     * The free-text draft fields the guide may read and fill.
     */
    public const array TEXT_FIELDS = ['name', 'description', 'detailDescription', 'systemPrompt', 'greeting', 'starterPrompts'];

    /**
     * The settings fields the guide may read and fill, keyed by draft field, with their `assistant_settings` key.
     */
    public const array SETTING_FIELDS = [
        'language' => 'language',
        'formality' => 'formality',
        'answerStyle' => WellKnownAssistantSettingKeys::ANSWER_STYLE,
    ];

    /**
     * What each settings field means, for the structured-output schema.
     */
    public const array SETTING_DESCRIPTIONS = [
        'language' => 'Language the assistant answers in.',
        'formality' => 'How formal the assistant\'s tone is.',
        'answerStyle' => 'How long and detailed the assistant\'s answers are.',
    ];

    /**
     * Every draft field the guide may read and fill.
     */
    public const array FIELDS = [...self::TEXT_FIELDS, 'handle', 'categoryId', 'model', 'language', 'formality', 'answerStyle', 'tags', 'avatar'];

    /**
     * Fields the builder requires before the assistant can be released (see `builderValidationRules.ts`).
     */
    public const array REQUIRED_FIELDS = ['name', 'handle', 'description', 'categoryId', 'systemPrompt', 'model'];

    /**
     * Validation rules for a submitted draft. Same catalog, same constraints
     * the structured-output schema enumerates — request validation and the
     * agent's schema are generated from one list. The prefix adapts the keys
     * to where the draft rides in a given request body (`draft.*` for the
     * builder's own payloads, `payload.draft.*` inside stream payloads).
     *
     * @return array<string, list<string>>
     */
    public static function draftRules(string $prefix = 'draft'): array
    {
        $rules = [
            'name' => ['nullable', 'string'],
            'handle' => ['nullable', 'string'],
            'categoryId' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'detailDescription' => ['nullable', 'string'],
            'systemPrompt' => ['nullable', 'string'],
            'greeting' => ['nullable', 'string'],
            'starterPrompts' => ['nullable', 'array'],
            'starterPrompts.*' => ['string'],
            'language' => ['nullable', 'string'],
            'formality' => ['nullable', 'string'],
            'answerStyle' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'avatar' => ['nullable', 'array'],
            'avatar.emoji' => ['nullable', 'string', 'max:32'],
            'avatar.background' => ['nullable', 'string', 'max:64'],
        ];

        $prefixed = [];

        foreach ($rules as $key => $fieldRules) {
            $prefixed[$prefix . '.' . $key] = $fieldRules;
        }

        return $prefixed;
    }
}
