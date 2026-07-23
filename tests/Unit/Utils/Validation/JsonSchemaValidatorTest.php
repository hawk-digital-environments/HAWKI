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

    public function testItReturnsArrayWhenDataMatchesArraySchema(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['name' => $this->factory->string()->required()],
            ['name' => 'Alice'],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsArrayWhenOptionalFieldIsAbsent(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['name' => $this->factory->string()->required(), 'age' => $this->factory->integer()],
            ['name' => 'Alice'],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsArrayForEmptySchema(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate([], []);

        static::assertIsArray($result);
    }

    // =========================================================================
    // Array schema — validation failures
    // =========================================================================

    public function testItReturnsErrorStringWhenRequiredFieldIsMissing(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['city' => $this->factory->string()->required()],
            [],
        );

        static::assertIsString($result);
        static::assertNotEmpty($result);
    }

    public function testItReturnsErrorStringWhenFieldTypeIsWrong(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['count' => $this->factory->integer()->required()],
            ['count' => 'not-an-integer'],
        );

        static::assertIsString($result);
    }

    public function testItReturnsErrorStringWhenEnumValueIsDisallowed(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['unit' => $this->factory->string()->enum(['celsius', 'fahrenheit'])->required()],
            ['unit' => 'kelvin'],
        );

        static::assertIsString($result);
    }

    public function testItReturnsArrayWhenEnumValueIsAllowed(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['unit' => $this->factory->string()->enum(['celsius', 'fahrenheit'])->required()],
            ['unit' => 'celsius'],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsErrorStringWhenStringIsTooShort(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['code' => $this->factory->string()->min(5)->required()],
            ['code' => 'ab'],
        );

        static::assertIsString($result);
    }

    public function testItReturnsArrayWhenStringMeetsMinLength(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['code' => $this->factory->string()->min(3)->required()],
            ['code' => 'abc'],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsErrorStringWhenStringExceedsMaxLength(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['tag' => $this->factory->string()->max(5)->required()],
            ['tag' => 'toolongvalue'],
        );

        static::assertIsString($result);
    }

    public function testItReturnsErrorStringWhenIntegerIsBelowMinimum(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['age' => $this->factory->integer()->min(18)->required()],
            ['age' => 5],
        );

        static::assertIsString($result);
    }

    public function testItReturnsArrayWhenIntegerMeetsMinimum(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['age' => $this->factory->integer()->min(18)->required()],
            ['age' => 18],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsErrorStringWhenIntegerExceedsMaximum(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['score' => $this->factory->integer()->max(100)->required()],
            ['score' => 101],
        );

        static::assertIsString($result);
    }

    public function testItReturnsArrayForNullableFieldSetToNull(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['note' => $this->factory->string()->nullable()->required()],
            ['note' => null],
        );

        static::assertEquals(['note' => null], $result);
    }

    // =========================================================================
    // Nested objects
    // =========================================================================

    public function testItReturnsArrayWhenNestedObjectIsValid(): void
    {
        $sut = new JsonSchemaValidator();

        $schema = [
            'location' => $this->factory->object([
                'lat' => $this->factory->number()->required(),
                'lon' => $this->factory->number()->required(),
            ])->required(),
        ];

        $result = $sut->validate($schema, ['location' => ['lat' => 52.5, 'lon' => 13.4]]);

        static::assertIsArray($result);
    }

    public function testItReturnsErrorStringWhenNestedObjectIsMissingRequiredField(): void
    {
        $sut = new JsonSchemaValidator();

        $schema = [
            'location' => $this->factory->object([
                'lat' => $this->factory->number()->required(),
                'lon' => $this->factory->number()->required(),
            ])->required(),
        ];

        $result = $sut->validate($schema, ['location' => ['lat' => 52.5]]);

        static::assertIsString($result);
    }

    // =========================================================================
    // Arrays
    // =========================================================================

    public function testItReturnsArrayWhenArrayItemsAreValid(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['tags' => $this->factory->array()->items($this->factory->string())->required()],
            ['tags' => ['php', 'laravel']],
        );

        static::assertIsArray($result);
    }

    public function testItReturnsErrorStringWhenArrayHasTooFewItems(): void
    {
        $sut = new JsonSchemaValidator();

        $result = $sut->validate(
            ['tags' => $this->factory->array()->min(2)->required()],
            ['tags' => ['only-one']],
        );

        static::assertIsString($result);
    }

    // =========================================================================
    // String schema
    // =========================================================================

    public function testItReturnsArrayWhenDataMatchesStringSchema(): void
    {
        $sut = new JsonSchemaValidator();

        $schemaJson = json_encode([
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
        ]);

        $result = $sut->validate($schemaJson, ['name' => 'Bob']);

        static::assertIsArray($result);
    }

    public function testItReturnsErrorStringWhenDataFailsStringSchema(): void
    {
        $sut = new JsonSchemaValidator();

        $schemaJson = json_encode([
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
        ]);

        $result = $sut->validate($schemaJson, []);

        static::assertIsString($result);
    }

    // =========================================================================
    // Data provider — multiple field scenarios
    // =========================================================================

    #[DataProvider('provideTestItValidatesMultipleFieldsData')]
    public function testItValidatesMultipleFields(array $data, bool $expectsSuccess): void
    {
        $sut = new JsonSchemaValidator();

        $schema = [
            'name' => $this->factory->string()->required(),
            'age' => $this->factory->integer()->min(0)->required(),
            'email' => $this->factory->string(),
        ];

        $result = $sut->validate($schema, $data);

        if ($expectsSuccess) {
            static::assertIsArray($result);
        } else {
            static::assertIsString($result);
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
