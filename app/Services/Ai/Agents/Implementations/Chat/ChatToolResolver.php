<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Implementations\Chat;


use App\Services\Ai\Agents\Exceptions\InvalidToolTransferStringException;
use App\Services\Ai\Agents\Implementations\Chat\Values\ToolTransferData;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Capabilities\AiModelCapabilityRegistry;
use App\Services\Ai\Tools\LaravelAi\LaravelToolResolver;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\ProviderTool;

/**
 * Resolves a list of frontend tool-transfer strings into concrete {@see Tool} or
 * {@see ProviderTool} instances ready to be passed to an agent.
 *
 * Tool-transfer strings use a colon-separated format understood by {@see ToolTransferData}:
 *
 * | Format                               | Meaning                                                          |
 * |--------------------------------------|------------------------------------------------------------------|
 * | `capability:<key>:auto[:<settings>]` | Let the model's native_capabilities decide: native if available, HAWKI tool otherwise. |
 * | `capability:<key>:native[:<settings>]` | Force the provider's own native implementation of the capability. |
 * | `capability:<key>:<toolName>[:<settings>]` | Use a specific HAWKI tool name for the capability. |
 * | `<toolName>[:<settings>]`            | Resolve a HAWKI tool directly by name.                           |
 *
 * The `<settings>` segment, when present, must be a JSON object string and is forwarded to
 * the underlying tool factory as a configuration array.
 *
 * Used by {@see ChatAgentFromLegacyRequestFactory} to materialise the `tools` array from the
 * legacy request payload.
 */
readonly class ChatToolResolver
{
    public function __construct(
        private AiModelCapabilityRegistry $capabilityRegistry,
        private LaravelToolResolver       $laravelToolResolver
    )
    {
    }

    /**
     * Resolves each tool-transfer string in $toolTransferStrings into a tool instance.
     *
     * Yields results lazily so the caller (typically an agent constructor) can consume them
     * without materialising the full list upfront.
     *
     * @param string[] $toolTransferStrings Serialised tool descriptors from the frontend payload.
     * @throws InvalidToolTransferStringException when an entry is not a string, has an unrecognised
     *         type prefix, references an undeclared capability, or contains invalid JSON settings.
     */
    public function findTools(
        array               $toolTransferStrings,
        AgentRequestContext $context
    ): iterable
    {
        foreach ($toolTransferStrings as $toolTransferString) {
            if (!is_string($toolTransferString)) {
                throw InvalidToolTransferStringException::forNotAString();
            }

            $toolData = ToolTransferData::fromString($toolTransferString);

            if ($toolData->isCapability()) {
                yield $this->findToolByCapability($toolData, $context);
                continue;
            }

            if ($toolData->isTool()) {
                yield $this->findToolByName($toolData, $context);
                continue;
            }

            throw InvalidToolTransferStringException::forInvalidType($toolTransferString);
        }
    }

    /**
     * Resolves a capability transfer string to the appropriate tool implementation.
     *
     * The `auto` inner-tool keyword checks whether the model declares native support for the
     * capability; if so, the provider's native tool is preferred over a HAWKI MCP/PHP tool.
     * The `native` keyword forces the provider's own implementation regardless of model flags.
     * Any other inner-tool value is treated as an explicit HAWKI tool name.
     */
    private function findToolByCapability(
        ToolTransferData    $toolData,
        AgentRequestContext $context
    ): Tool|ProviderTool
    {
        $capability = $this->capabilityRegistry->getDefinition($toolData->toolOrCapability);
        if (!$capability) {
            throw InvalidToolTransferStringException::forCapabilityNotFound($toolData->toolOrCapability);
        }

        if (!$toolData->innerTool) {
            throw InvalidToolTransferStringException::forCapabilityMissingInnerTool($toolData->toolOrCapability);
        }

        $innerTool = $toolData->innerTool;
        if ($innerTool === 'auto') {
            if ($context->model->native_capabilities->has($capability->key)) {
                return $this->laravelToolResolver->resolveNativeToolForCapability($capability->key, $context, $toolData->settings);
            }

            return $this->laravelToolResolver->resolveToolForCapability($capability->key, $context, $toolData->settings);
        }

        if ($toolData->innerTool === 'native') {
            return $this->laravelToolResolver->resolveNativeToolForCapability($capability->key, $context, $toolData->settings);
        }

        return $this->laravelToolResolver->resolveToolByName($toolData->innerTool, $context, $toolData->settings);
    }

    private function findToolByName(
        ToolTransferData    $toolData,
        AgentRequestContext $context
    ): Tool
    {
        return $this->laravelToolResolver->resolveToolByName($toolData->toolOrCapability, $context, $toolData->settings);
    }
}
