<?php

use App\Services\Ai\ConfigFileSync\ConfigFileSyncer;
use App\Services\Ai\StatusCheck\McpServerStatusUpdater;
use App\Services\Ai\StatusCheck\ModelStatusUpdater;
use App\Services\Ai\Tools\FunctionToolSyncer;
use App\Services\Ai\Tools\Mcp\McpToolSyncer;
use App\Services\System\Container\ServiceLocatorTrait;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    use ServiceLocatorTrait;

    public function up(): void
    {
        /** @var ConfigFileSyncer $configFileSyncer */
        $configFileSyncer = $this->getService(ConfigFileSyncer::class);
        $metrics = $configFileSyncer->sync(true);
        if ($metrics->hasErrors()) {
            throw new \RuntimeException("Errors during AI models sync: " . implode(", ", $metrics->getErrors()));
        }

        /** @var FunctionToolSyncer $functionSyncer */
        $functionSyncer = $this->getService(FunctionToolSyncer::class);
        $metrics = $functionSyncer->sync();
        if ($metrics->hasErrors()) {
            throw new \RuntimeException("Errors during function tool sync: " . implode(", ", $metrics->getErrors()));
        }

        /** @var McpServerStatusUpdater $mcpServerStatusUpdater */
        $mcpServerStatusUpdater = $this->getService(McpServerStatusUpdater::class);
        $metrics = $mcpServerStatusUpdater->run();
        if ($metrics->hasErrors()) {
            throw new \RuntimeException("Errors during MCP server status update: " . implode(", ", $metrics->getErrors()));
        }

        /** @var McpToolSyncer $mcpToolSyncer */
        $mcpToolSyncer = $this->getService(McpToolSyncer::class);
        $metrics = $mcpToolSyncer->sync();
        if ($metrics->hasErrors()) {
            throw new \RuntimeException("Errors during MCP tools sync: " . implode(", ", $metrics->getErrors()));
        }

        /** @var ModelStatusUpdater $modelStatusUpdater */
        $modelStatusUpdater = $this->getService(ModelStatusUpdater::class);
        $metrics = $modelStatusUpdater->run();
        if ($metrics->hasErrors()) {
            throw new \RuntimeException("Errors during model status update: " . implode(", ", $metrics->getErrors()));
        }
    }

    public function down(): void
    {
    }
};
