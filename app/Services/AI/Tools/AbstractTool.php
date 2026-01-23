<?php
declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Services\AI\Tools\Interfaces\ToolInterface;
use App\Services\AI\Value\AiModel;

/**
 * Abstract base class for tools to reduce boilerplate
 */
abstract class AbstractTool implements ToolInterface
{
    /**
     * List of provider classes this tool is available for.
     * Empty array means available for all providers.
     *
     * @var array<string>
     */
    protected array $availableProviders = [];

    /**
     * @inheritDoc
     */
    public function isAvailableForProvider(string $providerClass): bool
    {
        // If no providers specified, available for all
        if (empty($this->availableProviders)) {
            return true;
        }

        // Check if the provider is in the list
        foreach ($this->availableProviders as $provider) {
            if (str_contains($providerClass, $provider)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function isEnabledForModel(AiModel $model): bool
    {
        // Check if model supports function calling
        if (!$model->hasTool('function_calling')) {
            return false;
        }

        // Check if this specific tool is enabled for the model
        $enabledTools = $model->getTools()['enabled_tools'] ?? [];

        // If enabled_tools is not specified or is empty, all tools are enabled
        if (empty($enabledTools)) {
            return true;
        }

        return in_array($this->getName(), $enabledTools, true);
    }
}
