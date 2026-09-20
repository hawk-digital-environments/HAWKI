<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Corpus;

use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Implementations\Legacy\LegacyFormatter;
use App\Services\Ai\Formatters\Implementations\Legacy\LegacyStreamContext;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\HydratesResponses;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\LoadsCorpusFixtures;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\NormalizesIr;

/**
 * Round-trip corpus for the `legacy` formatter (N3): parse-fidelity (Track 1) and
 * response-fidelity (Track 2) snapshots. The emit-replay and cross-format tracks do
 * not apply — legacy NDJSON responses are not request payloads and the dialect is not
 * a spec shape.
 */
#[CoversClass(LegacyFormatter::class)]
#[CoversClass(LegacyStreamContext::class)]
class LegacyCorpusTest extends TestCase
{
    use HydratesResponses;
    use LoadsCorpusFixtures;
    use NormalizesIr;

    private LegacyFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new LegacyFormatter(
            userRepository: self::createStub(\App\Services\Users\Repositories\UserRepository::class),
            avatarStorage: self::createStub(\App\Services\Storage\AvatarStorageService::class),
        );
    }

    #[DataProvider('legacyRequestFixtures')]
    public function testRequestFixtureParsesToItsIrSnapshot(string $path): void
    {
        $fixture = self::loadFixture($path);

        try {
            $parsed = $this->formatter->parseRequest($this->request($fixture['wireRequest']));
        } catch (FormatterRequestException $exception) {
            self::assertErrorMatches($fixture['expectedError'] ?? null, $exception, $path);

            return;
        }

        self::assertArrayNotHasKey('expectedError', $fixture, "Fixture {$path} expected an error but the request parsed.");

        self::assertIrSnapshotEquals($fixture['expectedIr'], self::normalizeRequest($parsed), basename($path));
    }

    #[DataProvider('legacyResponseFixtures')]
    public function testResponseFixtureFormatsToItsWireSnapshot(string $path): void
    {
        $fixture = self::loadFixture($path);

        $wire = $this->formatter->formatResponse(self::hydrateResponse($fixture['irResponse']))->getData(true);

        self::assertIrSnapshotEquals($fixture['expectedWireResponse'], $wire, basename($path));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function legacyRequestFixtures(): array
    {
        return self::corpusProvider('legacy/requests');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function legacyResponseFixtures(): array
    {
        return self::corpusProvider('legacy/responses');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): Request
    {
        return Request::create(
            '/req/streamAI',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($body),
        );
    }

    /**
     * @param array<string, mixed>|null $expected
     */
    private static function assertErrorMatches(?array $expected, FormatterRequestException $exception, string $path): void
    {
        self::assertNotNull($expected, "Fixture {$path} parsed with an unexpected error: {$exception->errorCode()} — {$exception->getMessage()}");
        self::assertSame($expected['code'], $exception->errorCode(), basename($path));

        if (\array_key_exists('param', $expected)) {
            self::assertSame($expected['param'], $exception->param(), basename($path));
        }
    }
}
