<?php
declare(strict_types=1);

namespace Tests\Unit\Services\FileConverter\Exception;

use App\Services\FileConverter\Exception\FileConverterExceptionInterface;
use App\Services\FileConverter\Exception\InvalidFileConverterConfigException;
use App\Services\FileConverter\Interfaces\FileConverterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidFileConverterConfigException::class)]
class InvalidFileConverterConfigExceptionTest extends TestCase
{
    // =========================================================================
    // Interface contract
    // =========================================================================

    public function testItImplementsFileConverterExceptionInterface(): void
    {
        $sut = InvalidFileConverterConfigException::forInvalidConverterType('unknown');
        static::assertInstanceOf(FileConverterExceptionInterface::class, $sut);
    }

    public function testItExtendsInvalidArgumentException(): void
    {
        $sut = InvalidFileConverterConfigException::forInvalidConverterType('unknown');
        static::assertInstanceOf(\InvalidArgumentException::class, $sut);
    }

    // =========================================================================
    // forInvalidConverterType
    // =========================================================================

    public function testItForInvalidConverterTypeIncludesTypeName(): void
    {
        $sut = InvalidFileConverterConfigException::forInvalidConverterType('my_converter');

        static::assertStringContainsString('my_converter', $sut->getMessage());
    }

    // =========================================================================
    // forMissingClassInConfig
    // =========================================================================

    public function testItForMissingClassInConfigIncludesTypeName(): void
    {
        $sut = InvalidFileConverterConfigException::forMissingClassInConfig('kreuzberg');

        static::assertStringContainsString('kreuzberg', $sut->getMessage());
    }

    public function testItForMissingClassInConfigMentionsMissingClassKey(): void
    {
        $sut = InvalidFileConverterConfigException::forMissingClassInConfig('kreuzberg');

        static::assertStringContainsString('class', $sut->getMessage());
    }

    // =========================================================================
    // forInvalidClassInConfig
    // =========================================================================

    public function testItForInvalidClassInConfigIncludesTypeName(): void
    {
        $sut = InvalidFileConverterConfigException::forInvalidClassInConfig('kreuzberg', 'NonExistentClass');

        static::assertStringContainsString('kreuzberg', $sut->getMessage());
    }

    public function testItForInvalidClassInConfigIncludesClassName(): void
    {
        $sut = InvalidFileConverterConfigException::forInvalidClassInConfig('kreuzberg', 'NonExistentClass');

        static::assertStringContainsString('NonExistentClass', $sut->getMessage());
    }

    public function testItForInvalidClassInConfigMentionsRequiredInterface(): void
    {
        $sut = InvalidFileConverterConfigException::forInvalidClassInConfig('kreuzberg', 'NonExistentClass');

        static::assertStringContainsString(FileConverterInterface::class, $sut->getMessage());
    }
}
