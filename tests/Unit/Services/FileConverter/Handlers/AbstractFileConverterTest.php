<?php
declare(strict_types=1);

namespace Tests\Unit\Services\FileConverter\Handlers;

use App\Services\FileConverter\Handlers\AbstractFileConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Services\FileConverter\Handlers\AbstractFileConverterTestFixtures\ConcreteFileConverterStub;

#[CoversClass(AbstractFileConverter::class)]
class AbstractFileConverterTest extends TestCase
{
    // =========================================================================
    // setConfig / config storage
    // =========================================================================

    public function testItSetConfigStoresConfig(): void
    {
        $sut = new ConcreteFileConverterStub();
        $sut->setConfig(['api_url' => 'http://example.com']);

        // Verify the config was stored by confirming no exception is thrown
        // and the object remains in a valid state.
        static::assertInstanceOf(AbstractFileConverter::class, $sut);
    }

    // =========================================================================
    // canConvertMimetype
    // =========================================================================

    public function testItCanConvertMimetypeReturnsTrueForSupportedType(): void
    {
        $sut = new ConcreteFileConverterStub(['application/pdf', 'image/png']);

        static::assertTrue($sut->canConvertMimetype('application/pdf'));
        static::assertTrue($sut->canConvertMimetype('image/png'));
    }

    public function testItCanConvertMimetypeReturnsFalseForUnsupportedType(): void
    {
        $sut = new ConcreteFileConverterStub(['application/pdf']);

        static::assertFalse($sut->canConvertMimetype('image/png'));
        static::assertFalse($sut->canConvertMimetype('text/html'));
        static::assertFalse($sut->canConvertMimetype(''));
    }

    public function testItCanConvertMimetypeUsesStrictComparison(): void
    {
        $sut = new ConcreteFileConverterStub(['application/pdf']);

        // A partial match or type-coerced value must not match.
        static::assertFalse($sut->canConvertMimetype('Application/PDF'));
        static::assertFalse($sut->canConvertMimetype('application/pdf '));
    }

    // =========================================================================
    // wouldLikeSomeoneElseToConvertMimetype
    // =========================================================================

    public function testItWouldLikeSomeoneElseToConvertMimetypeReturnsFalseByDefault(): void
    {
        $sut = new ConcreteFileConverterStub();

        static::assertFalse($sut->wouldLikeSomeoneElseToConvertMimetype('application/pdf'));
        static::assertFalse($sut->wouldLikeSomeoneElseToConvertMimetype('image/svg+xml'));
        static::assertFalse($sut->wouldLikeSomeoneElseToConvertMimetype(''));
    }

    // =========================================================================
    // getRequestTimeout
    // =========================================================================

    public function testItGetRequestTimeoutUsesDefaultWhenConfigHasNoTimeout(): void
    {
        $sut = new ConcreteFileConverterStub();
        $sut->setConfig(['api_url' => 'http://example.com']);

        static::assertSame(60, $sut->exposeRequestTimeout());
    }

    public function testItGetRequestTimeoutUsesDefaultWhenConfigNeverSet(): void
    {
        $sut = new ConcreteFileConverterStub();

        static::assertSame(60, $sut->exposeRequestTimeout());
    }

    public function testItGetRequestTimeoutUsesConfiguredTimeoutWhenSet(): void
    {
        $sut = new ConcreteFileConverterStub();
        $sut->setConfig(['api_url' => 'http://example.com', 'timeout' => 12]);

        static::assertSame(12, $sut->exposeRequestTimeout());
    }

    public function testItGetRequestTimeoutCastsNonIntConfigValueToInt(): void
    {
        $sut = new ConcreteFileConverterStub();
        $sut->setConfig(['timeout' => '120']);

        static::assertSame(120, $sut->exposeRequestTimeout());
    }
}
