<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\Validation;

use App\Utils\JsonSchema\JsonSchemaValidator;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(JsonSchemaValidator::class)]
class JsonSchemaValidatorTest extends TestCase
{
    private JsonSchemaTypeFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new JsonSchemaTypeFactory();
    }

    public function testItConstructs(): void
    {
        $sut = new JsonSchemaValidator();
        static::assertInstanceOf(JsonSchemaValidator::class, $sut);
    }

    // =========================================================================
    // Array schema — happy path
    // =========================================================================

    public function testItReturnsNullWhenDataMatchesArraySchema(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['name' => $this->factory->string()->required()],
            ['name' => 'Alice'],
        );

        static::assertNull($result);
    }

    public function testItReturnsNullWhenOptionalFieldIsAbsent(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['name' => $this->factory->string()->required(), 'age' => $this->factory->integer()],
            ['name' => 'Alice'],
        );

        static::assertNull($result);
    }

    public function testItReturnsNullForEmptySchema(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate([], ['anything' => 'goes']);

        static::assertNull($result);
    }

    // =========================================================================
    // Array schema — validation failures
    // =========================================================================

    public function testItReturnsErrorsWhenRequiredFieldIsMissing(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['city' => $this->factory->string()->required()],
            [],
        );

        static::assertIsArray($result);
        static::assertNotEmpty($result);
    }

    public function testItReturnsErrorsWhenFieldTypeIsWrong(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['count' => $this->factory->integer()->required()],
            ['count' => 'not-an-integer'],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsErrorsWhenEnumValueIsDisallowed(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['unit' => $this->factory->string()->enum(['celsius', 'fahrenheit'])->required()],
            ['unit' => 'kelvin'],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsNullWhenEnumValueIsAllowed(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['unit' => $this->factory->string()->enum(['celsius', 'fahrenheit'])->required()],
            ['unit' => 'celsius'],
        );

        static::assertNull($result);
    }

    public function testItReturnsErrorsWhenStringIsTooShort(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['code' => $this->factory->string()->min(5)->required()],
            ['code' => 'ab'],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsNullWhenStringMeetsMinLength(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['code' => $this->factory->string()->min(3)->required()],
            ['code' => 'abc'],
        );

        static::assertNull($result);
    }

    public function testItReturnsErrorsWhenStringExceedsMaxLength(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['tag' => $this->factory->string()->max(5)->required()],
            ['tag' => 'toolongvalue'],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsErrorsWhenIntegerIsBelowMinimum(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['age' => $this->factory->integer()->min(18)->required()],
            ['age' => 5],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsNullWhenIntegerMeetsMinimum(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['age' => $this->factory->integer()->min(18)->required()],
            ['age' => 18],
        );

        static::assertNull($result);
    }

    public function testItReturnsErrorsWhenIntegerExceedsMaximum(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['score' => $this->factory->integer()->max(100)->required()],
            ['score' => 101],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsNullForNullableFieldSetToNull(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['note' => $this->factory->string()->nullable()->required()],
            ['note' => null],
        );

        static::assertNull($result);
    }

    // =========================================================================
    // Nested objects
    // =========================================================================

    public function testItReturnsNullWhenNestedObjectIsValid(): void
    {
        $sut = new JsonSchemaValidator();

        $schema = [
            'location' => $this->factory->object([
                'lat' => $this->factory->number()->required(),
                'lon' => $this->factory->number()->required(),
            ])->required(),
        ];

        $result = $sut->validate($schema, ['location' => ['lat' => 52.5, 'lon' => 13.4]]);

        static::assertNull($result);
    }

    public function testItReturnsErrorsWhenNestedObjectIsMissingRequiredField(): void
    {
        $sut = new JsonSchemaValidator();

        $schema = [
            'location' => $this->factory->object([
                'lat' => $this->factory->number()->required(),
                'lon' => $this->factory->number()->required(),
            ])->required(),
        ];

        $result = $sut->validate($schema, ['location' => ['lat' => 52.5]]);

        static::assertIsArray($result);
    }

    // =========================================================================
    // Arrays
    // =========================================================================

    public function testItReturnsNullWhenArrayItemsAreValid(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['tags' => $this->factory->array()->items($this->factory->string())->required()],
            ['tags' => ['php', 'laravel']],
        );

        static::assertNull($result);
    }

    public function testItReturnsErrorsWhenArrayHasTooFewItems(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['tags' => $this->factory->array()->min(2)->required()],
            ['tags' => ['only-one']],
        );

        static::assertIsArray($result);
    }

    // =========================================================================
    // String schema
    // =========================================================================

    public function testItReturnsNullWhenDataMatchesStringSchema(): void
    {
        $sut = new JsonSchemaValidator();

        $schemaJson = json_encode([
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
        ]);

        $result = $sut->validate($schemaJson, ['name' => 'Bob']);

        static::assertNull($result);
    }

    public function testItReturnsErrorsWhenDataFailsStringSchema(): void
    {
        $sut = new JsonSchemaValidator();

        $schemaJson = json_encode([
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
        ]);

        $result = $sut->validate($schemaJson, []);

        static::assertIsArray($result);
    }

    // =========================================================================
    // Data provider — multiple field scenarios
    // =========================================================================

    #[DataProvider('provideTestItValidatesMultipleFieldsData')]
    public function testItValidatesMultipleFields(array $data, bool $expectsNull): void
    {
        $sut = new JsonSchemaValidator();

        $schema = [
            'name' => $this->factory->string()->required(),
            'age' => $this->factory->integer()->min(0)->required(),
            'email' => $this->factory->string(),
        ];

        $result = $sut->validate($schema, $data);

        if ($expectsNull) {
            static::assertNull($result);
        } else {
            static::assertIsArray($result);
        }
    }

    public static function provideTestItValidatesMultipleFieldsData(): iterable
    {
        yield 'all required fields present and valid' => [['name' => 'Alice', 'age' => 30], true];
        yield 'optional email also present' => [['name' => 'Bob', 'age' => 25, 'email' => 'b@example.com'], true];
        yield 'missing required name' => [['age' => 30], false];
        yield 'missing required age' => [['name' => 'Alice'], false];
        yield 'age below minimum' => [['name' => 'Alice', 'age' => -1], false];
        yield 'age as string instead of integer' => [['name' => 'Alice', 'age' => 'thirty'], false];
    }
}
