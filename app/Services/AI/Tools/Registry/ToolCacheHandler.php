<?php

namespace App\Services\AI\Tools\Registry;

use App\Services\AI\Tools\Value\ToolDefinition;
use Exception;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

#[Singleton]
class ToolCacheHandler
{
    /**
     * Load discovered tools from cache
     *
     * @return array|null Array of tool data or null if cache miss
     */
    public function loadToolsFromCache(string $cachePath): ?array
    {
        if (!file_exists($cachePath)) {
            return null;
        }

        try {
            $cached = require $cachePath;

            // Validate cache structure
            if (!is_array($cached) || !isset($cached['version'], $cached['timestamp'], $cached['tools'])) {
                Log::warning('Invalid MCP tools cache structure, will rebuild');
                return null;
            }

            // Check cache age (default: 1 hour)
            $maxAge = config('tools.mcp_cache_ttl', 3600);
            if (time() - $cached['timestamp'] > $maxAge) {
                return null;
            }

            // Reconstruct ToolDefinition objects
            foreach ($cached['tools'] as &$toolData) {
                $def = $toolData['definition'];
                $toolData['definition'] = new ToolDefinition(
                    name: $def['name'],
                    description: $def['description'],
                    parameters: $def['parameters']
                );
            }

            return $cached['tools'];
        } catch (Exception $e) {
            Log::warning('Failed to load MCP tools cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Save discovered tools to cache
     */
    public function saveToolsToCache(string $cachePath, array $tools): void
    {
        try {
            // Ensure directory exists
            $dir = dirname($cachePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Prepare data for caching (serialize ToolDefinition)
            $cacheData = [
                'version' => 1,
                'timestamp' => time(),
                'tools' => array_map(function ($toolData) {
                    return [
                        'name' => $toolData['name'],
                        'definition' => [
                            'name' => $toolData['definition']->name,
                            'description' => $toolData['definition']->description,
                            'parameters' => $toolData['definition']->parameters,
                        ],
                        'mcp_tool_name' => $toolData['mcp_tool_name'],
                        'server_config' => $toolData['server_config'],
                        'server_key' => $toolData['server_key'],
                    ];
                }, $tools),
            ];

            // Write cache file
            $content = '<?php return ' . var_export($cacheData, true) . ';';
            file_put_contents($cachePath, $content, LOCK_EX);

        } catch (Exception $e) {
            Log::warning('Failed to save MCP tools cache: ' . $e->getMessage());
        }
    }
}
