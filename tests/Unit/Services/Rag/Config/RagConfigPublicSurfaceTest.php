<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Rag\Config;

use App\Models\User;
use App\Services\Rag\AssistantKnowledge\AgentTools\RagKnowledgeAgentTool;
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
            'datasetPrefix' => 'assistant_',
        ]);

        $public = $config->toPublicArray($this->authenticatedRequest());

        static::assertSame([
            'enabled' => true,
            'fileKnowledgeTool' => RagKnowledgeAgentTool::TOOL_NAME,
        ], $public);
    }

    public function testItExposesNothingToGuests(): void
    {
        $config = RagConfig::fromArray(['enabled' => true]);

        static::assertNull($config->toPublicArray(Request::create('/')));
    }

    private function authenticatedRequest(): Request
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => new User);

        return $request;
    }
}
