<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Rag\Config;

use App\Models\User;
use App\Services\Rag\Config\RagConfig;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RagConfig::class)]
class RagConfigPublicSurfaceTest extends TestCase
{
    public function testItExposesTheEnabledFlagAndFileKnowledgeToolNameToAuthenticatedUsers(): void
    {
        $config = RagConfig::fromArray([
            'enabled' => true,
            'queryToolName' => 'hawki-rag-query-search',
        ]);

        $public = $config->toPublicArray($this->authenticatedRequest());

        static::assertSame([
            'enabled' => true,
            'fileKnowledgeTool' => 'hawki-rag-query-search',
        ], $public);
    }

    public function testItExposesNothingToGuests(): void
    {
        $config = RagConfig::fromArray(['enabled' => true]);

        static::assertNull($config->toPublicArray(Request::create('/')));
    }

    public function testItReadsTheToolIdentitiesFromTheFlatConfigKeys(): void
    {
        config(['rag.query_tool' => 'custom-kb-search', 'rag.web_search_tool' => 'custom-web-search']);

        $config = RagConfig::make(\config());

        static::assertSame('custom-kb-search', $config->queryToolName);
        static::assertSame('custom-web-search', $config->webSearchToolName);
    }

    private function authenticatedRequest(): Request
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => new User);

        return $request;
    }
}
