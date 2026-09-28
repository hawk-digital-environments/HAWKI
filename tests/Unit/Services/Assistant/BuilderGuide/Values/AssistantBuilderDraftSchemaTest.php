<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant\BuilderGuide\Values;

use App\Services\Assistant\BuilderGuide\Values\AssistantBuilderDraftSchema;
use App\Services\Assistant\Values\AssistantFieldRules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssistantBuilderDraftSchema::class)]
class AssistantBuilderDraftSchemaTest extends TestCase
{
    public function testItListsEveryDraftFieldExactlyOnce(): void
    {
        $all = [...AssistantBuilderDraftSchema::TEXT_FIELDS, 'handle', 'categoryId', 'model',
            ...array_keys(AssistantBuilderDraftSchema::SETTING_FIELDS), 'tags', 'avatar'];
        self::assertSame($all, AssistantBuilderDraftSchema::FIELDS);
        self::assertSame(\count($all), \count(array_unique($all)), 'no field may appear twice');
    }

    public function testItDescribesEverySettingsField(): void
    {
        self::assertSame(
            array_keys(AssistantBuilderDraftSchema::SETTING_FIELDS),
            array_keys(AssistantBuilderDraftSchema::SETTING_DESCRIPTIONS),
            'every settings field carries a description for the structured-output schema',
        );
    }

    public function testItRequiresOnlyKnownFields(): void
    {
        self::assertSame(
            [],
            array_values(array_diff(AssistantBuilderDraftSchema::REQUIRED_FIELDS, AssistantBuilderDraftSchema::FIELDS)),
        );
    }

    public function testItValidatesEveryDraftField(): void
    {
        $validatedFields = array_map(
            static fn (string $rule): string => (string) preg_replace('/\.\*$/', '', $rule),
            array_keys(AssistantBuilderDraftSchema::draftRules()),
        );

        foreach (AssistantBuilderDraftSchema::FIELDS as $field) {
            self::assertContains('draft.' . $field, $validatedFields, "draft.{$field} must have validation rules");
        }
    }

    public function testItAnchorsTheSharedFieldConstraints(): void
    {
        $rules = AssistantBuilderDraftSchema::draftRules();

        self::assertContains('max:' . AssistantFieldRules::NAME_MAX_LENGTH, $rules['draft.name']);
        self::assertContains('max:' . AssistantFieldRules::HANDLE_MAX_LENGTH, $rules['draft.handle']);
        self::assertContains('regex:' . AssistantFieldRules::HANDLE_PATTERN, $rules['draft.handle']);
        self::assertContains('max:' . AssistantFieldRules::AVATAR_EMOJI_MAX_LENGTH, $rules['draft.avatar.emoji']);
    }

    public function testItInventsNoCountCaps(): void
    {
        $rules = AssistantBuilderDraftSchema::draftRules();

        // Tags and starter prompts are stored as uncounted rows (and their
        // text columns carry their own length); the draft must not invent
        // list-size limits the storage does not enforce.
        self::assertNotContains('max', $rules['draft.tags']);
        self::assertNotContains('max', $rules['draft.starterPrompts']);
        self::assertNotContains('max', $rules['draft.avatar.background']);
        self::assertSame(['string'], $rules['draft.starterPrompts.*']);
    }
}
