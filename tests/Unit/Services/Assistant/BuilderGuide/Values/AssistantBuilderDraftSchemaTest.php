<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant\BuilderGuide\Values;

use App\Services\Assistant\BuilderGuide\Values\AssistantBuilderDraftSchema;
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
}
