<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Providers\Adapters\ModelList;

use App\Services\Ai\Exceptions\ModelListResponseException;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListResponse;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ModelListResponse::class)]
class ModelListResponseTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $response = $this->makeResponse(['data' => []]);
        $sut = new ModelListResponse($response);
        static::assertInstanceOf(ModelListResponse::class, $sut);
    }

    public function testItExposesResponseProperty(): void
    {
        $response = $this->makeResponse(['data' => []]);
        $sut = new ModelListResponse($response);
        static::assertSame($response, $sut->response);
    }

    // =========================================================================
    // getAll
    // =========================================================================

    public function testItGetAllReturnsDecodedArray(): void
    {
        $sut = new ModelListResponse($this->makeResponse(['models' => ['a', 'b']]));
        static::assertSame(['models' => ['a', 'b']], $sut->getAll());
    }

    public function testItGetAllCachesResultOnSubsequentCalls(): void
    {
        $sut = new ModelListResponse($this->makeResponse(['key' => 'value']));
        $first = $sut->getAll();
        $second = $sut->getAll();
        static::assertSame($first, $second);
    }

    public function testItGetAllThrowsForInvalidJson(): void
    {
        $response = $this->makeRawResponse('not-valid-json');
        $sut = new ModelListResponse($response);
        $this->expectException(ModelListResponseException::class);
        $this->expectExceptionMessage('Failed to parse model list response as JSON: not-valid-json');
        $sut->getAll();
    }

    public function testItGetAllThrowsWhenTopLevelIsNotArray(): void
    {
        $response = $this->makeRawResponse('"just a string"');
        $sut = new ModelListResponse($response);
        $this->expectException(ModelListResponseException::class);
        $this->expectExceptionMessage('Model list response is not an array, got string.');
        $sut->getAll();
    }

    // =========================================================================
    // getOne
    // =========================================================================

    public function testItGetOneReturnsValueAtPath(): void
    {
        $sut = new ModelListResponse($this->makeResponse(['meta' => ['version' => '1.0']]));
        static::assertSame('1.0', $sut->getOne('meta.version'));
    }

    public function testItGetOneReturnsNullForMissingPath(): void
    {
        $sut = new ModelListResponse($this->makeResponse(['key' => 'value']));
        static::assertNull($sut->getOne('nonexistent.path'));
    }

    public function testItGetOneReturnsNullForPartiallyMissingPath(): void
    {
        $sut = new ModelListResponse($this->makeResponse(['meta' => []]));
        static::assertNull($sut->getOne('meta.missing'));
    }

    // =========================================================================
    // getList
    // =========================================================================

    public function testItGetListReturnsCollectionAtPath(): void
    {
        $sut = new ModelListResponse($this->makeResponse(['data' => ['id1', 'id2']]));
        $result = $sut->getList('data');
        static::assertSame(['id1', 'id2'], $result->toArray());
    }

    public function testItGetListSupportsWildcardPath(): void
    {
        $sut = new ModelListResponse($this->makeResponse([
            'data' => [
                ['id' => 'model-1'],
                ['id' => 'model-2'],
            ]
        ]));
        $result = $sut->getList('data.*');
        static::assertCount(2, $result);
    }

    public function testItGetListThrowsWhenPathValueIsNotArray(): void
    {
        $sut = new ModelListResponse($this->makeResponse(['data' => 'not-an-array']));
        $this->expectException(ModelListResponseException::class);
        $this->expectExceptionMessage('Extracted data at path "data" is not an array, got: "not-an-array"');
        $sut->getList('data');
    }

    public function testItGetListThrowsWhenPathIsMissing(): void
    {
        $sut = new ModelListResponse($this->makeResponse(['other' => []]));
        $this->expectException(ModelListResponseException::class);
        $this->expectExceptionMessage('Extracted data at path "data" is not an array, got: null');
        $sut->getList('data');
    }

    // =========================================================================
    // getMapped
    // =========================================================================

    public function testItGetMappedTransformsEachItem(): void
    {
        $sut = new ModelListResponse($this->makeResponse([
            'data' => [
                ['id' => 'model-1'],
                ['id' => 'model-2'],
            ]
        ]));
        $result = $sut->getMapped('data.*', fn(array $item) => strtoupper($item['id']));
        static::assertSame(['MODEL-1', 'MODEL-2'], $result->values()->toArray());
    }

    public function testItGetMappedFiltersOutNullReturnValues(): void
    {
        $sut = new ModelListResponse($this->makeResponse([
            'data' => [
                ['id' => 'keep'],
                ['id' => 'drop'],
            ]
        ]));
        $result = $sut->getMapped('data.*', function (array $item) {
            return $item['id'] === 'keep' ? $item['id'] : null;
        });
        static::assertCount(1, $result);
        static::assertSame('keep', $result->first());
    }

    public function testItGetMappedReturnsEmptyCollectionWhenAllItemsAreNull(): void
    {
        $sut = new ModelListResponse($this->makeResponse(['data' => [['id' => 'x']]]));
        $result = $sut->getMapped('data.*', fn() => null);
        static::assertCount(0, $result);
    }

    public function testItGetMappedThrowsWhenPathIsNotArray(): void
    {
        $sut = new ModelListResponse($this->makeResponse(['data' => 'not-an-array']));
        $this->expectException(ModelListResponseException::class);
        $sut->getMapped('data.*', fn($x) => $x);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeResponse(array $data): Response
    {
        return $this->makeRawResponse((string) json_encode($data));
    }

    private function makeRawResponse(string $body): Response
    {
        return new Response(new \GuzzleHttp\Psr7\Response(200, [], $body));
    }
}
