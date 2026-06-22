<?php
declare(strict_types=1);

namespace Tests\Unit\Casts;

use App\Casts\AsInstance;
use App\Casts\Contracts\CastableInstanceInterface;
use App\Casts\Exceptions\InvalidCastConfigurationException;
use App\Casts\Exceptions\InvalidCastValueException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Casts\AsInstanceTestFixtures\CastableStub;

#[CoversClass(AsInstance::class)]
#[CoversClass(InvalidCastConfigurationException::class)]
#[CoversClass(InvalidCastValueException::class)]
class AsInstanceTest extends TestCase
{
    // =========================================================================
    // castUsing() — configuration errors
    // =========================================================================

    public function testItCastUsingThrowsWhenArgumentsAreEmpty(): void
    {
        $this->expectException(InvalidCastConfigurationException::class);
        $this->expectExceptionMessage(sprintf(
            '%s can only be used with a class name argument, e.g. %s:App\\Services\\AI\\Values\\ModelIoList',
            AsInstance::class,
            AsInstance::class
        ));

        AsInstance::castUsing([]);
    }

    public function testItCastUsingThrowsForUnknownClass(): void
    {
        $this->expectException(InvalidCastConfigurationException::class);
        $this->expectExceptionMessage('Class [NonExistent\\Foo] does not exist for');

        AsInstance::castUsing([base64_encode('NonExistent\\Foo')]);
    }

    public function testItCastUsingThrowsWhenClassDoesNotImplementInterface(): void
    {
        $this->expectException(InvalidCastConfigurationException::class);
        $this->expectExceptionMessage(sprintf(
            'Class [%s] must implement %s',
            \stdClass::class,
            CastableInstanceInterface::class
        ));

        AsInstance::castUsing([base64_encode(\stdClass::class)]);
    }

    public function testItCastUsingReturnsCastsAttributesForValidClass(): void
    {
        $caster = AsInstance::castUsing([base64_encode(CastableStub::class)]);

        static::assertInstanceOf(CastsAttributes::class, $caster);
    }

    // =========================================================================
    // get()
    // =========================================================================

    public function testItGetHydratesInstanceFromJsonString(): void
    {
        $caster = AsInstance::castUsing([base64_encode(CastableStub::class)]);
        $model = $this->createMock(Model::class);

        $result = $caster->get($model, 'field', '{"name":"hello","value":42}', []);

        static::assertInstanceOf(CastableStub::class, $result);
        static::assertSame('hello', $result->name);
        static::assertSame(42, $result->value);
    }

    public function testItGetThrowsForNonStringValue(): void
    {
        $caster = AsInstance::castUsing([base64_encode(CastableStub::class)]);
        $model = $this->createMock(Model::class);

        $this->expectException(InvalidCastValueException::class);
        $this->expectExceptionMessage('Database value must be a JSON string, got a non-string value.');

        $caster->get($model, 'field', 123, []);
    }

    public function testItGetSilentlyHandlesNonDecodableJson(): void
    {
        // Truly unparseable JSON (null result from json_decode) is silently treated
        // as an empty array — this gracefully handles MySQL NULL JSON columns
        // stored as the literal string 'null' or completely invalid JSON blobs.
        $caster = AsInstance::castUsing([base64_encode(CastableStub::class)]);
        $model = $this->createMock(Model::class);

        $result = $caster->get($model, 'field', 'not-json', []);

        static::assertInstanceOf(CastableStub::class, $result);
        static::assertSame('', $result->name);
        static::assertSame(0, $result->value);
    }

    public function testItGetThrowsWhenJsonIsNotAnArray(): void
    {
        $caster = AsInstance::castUsing([base64_encode(CastableStub::class)]);
        $model = $this->createMock(Model::class);

        $this->expectException(InvalidCastValueException::class);
        $this->expectExceptionMessage('Database value could not be decoded as JSON array.');

        $caster->get($model, 'field', '"just-a-string"', []);
    }

    // =========================================================================
    // set()
    // =========================================================================

    public function testItSetSerializesInstanceToJsonString(): void
    {
        $caster = AsInstance::castUsing([base64_encode(CastableStub::class)]);
        $model = $this->createMock(Model::class);
        $stub = new CastableStub('hello', 42);

        $result = $caster->set($model, 'field', $stub, []);

        static::assertSame('{"name":"hello","value":42}', $result);
    }

    public function testItSetThrowsForNonCastableInstance(): void
    {
        $caster = AsInstance::castUsing([base64_encode(CastableStub::class)]);
        $model = $this->createMock(Model::class);

        $this->expectException(InvalidCastValueException::class);
        $this->expectExceptionMessage(sprintf(
            'Application value must be an instance of %s, got stdClass.',
            CastableInstanceInterface::class
        ));

        $caster->set($model, 'field', new \stdClass(), []);
    }

    // =========================================================================
    // of()
    // =========================================================================

    public function testItOfReturnsCastStringWithBase64EncodedClass(): void
    {
        $result = AsInstance::of(CastableStub::class);

        static::assertSame(
            AsInstance::class . ':' . base64_encode(CastableStub::class),
            $result
        );
    }
}
