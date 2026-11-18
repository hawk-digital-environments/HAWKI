<?php
declare(strict_types=1);


namespace Tests\Unit\Services\Ai\Tools\LaravelAi\ConvertedToolRunCeilingTest\ConvertedToolRunCeilingTestFixtures;


use App\Services\Ai\Tools\AbstractTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * HAWKI tool with a parameterisable per-run ceiling and a working invocation,
 * used to verify that a tool's own ceiling is respected by the conversion
 * pipeline even when it exceeds the model budget.
 */
class RunLimitedStubTool extends AbstractTool
{
    public function __construct(int $maxRuns)
    {
        $this->setMaxRuns($maxRuns);
    }

    public function name(): string
    {
        return 'run_limited_stub_tool';
    }

    public function description(): string
    {
        return 'Declares a run ceiling for conversion tests.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function __invoke(): string
    {
        return 'stub-ok';
    }
}
