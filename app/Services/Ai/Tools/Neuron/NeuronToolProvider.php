<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Neuron;


use App\Collections\AiToolCollection;
use App\Models\Ai\AiModel;
use App\Models\Ai\AiTool;
use App\Services\Ai\Registries\AiModelCapabilityRegistry;
use App\Services\Ai\Registries\ProviderAdapterRegistry;
use App\Services\Ai\Tools\Neuron\Events\ToolsResolvedFilterEvent;
use App\Services\Ai\Values\ModelCapabilityValueType;
use App\Services\Ai\Values\ParameterSource;
use Illuminate\Container\Attributes\Singleton;
use NeuronAI\Tools\ProviderToolInterface;
use NeuronAI\Tools\ToolInterface;
use Psr\Log\LoggerInterface;

/**
 * @api
 */
#[Singleton]
readonly class NeuronToolProvider
{
    public function __construct(
        private ProviderAdapterRegistry   $adapterRegistry,
        private AiModelCapabilityRegistry $capabilityRegistry,
        private NeuronToolConverter       $mcpToolConverter,
        private LoggerInterface           $logger
    )
    {
    }

    /**
     * Resolves the active Neuron tools for a single AI request.
     *
     * Two sources feed into the final list:
     *
     * **1. Capability-based tools** — For each requested capability name, the model's
     * {@see ModelCapabilities} rule ({@see ModelCapabilityValueType}) controls what happens:
     * - `NO`     → the capability and any tools registered for it are suppressed entirely.
     * - `NATIVE` → a provider-native tool is added if the adapter supplies one for that capability.
     * - `YES`    → a registered custom {@see AiTool} is preferred; falls back to the native
     *              provider tool when no custom tool is linked to the model.
     * - `TOOL`   → a registered custom {@see AiTool} is required; the native provider tool is ignored.
     *
     * **2. Explicitly requested tools** — Names from `$requestedTools` (matching `ai_tools.name`)
     * are added if they are linked to the model *and* their associated capability was not disabled
     * or forced to native in step 1.
     *
     * After both lists are merged, {@see ToolsResolvedFilterEvent} is dispatched, giving listeners
     * a final chance to add, remove, or reorder tools before the result is returned to the caller.
     *
     * Raw {@see AiTool} records are converted to Neuron-compatible instances by
     * {@see NeuronToolConverter}. Conversion failures are logged and the affected tool is skipped.
     *
     * Example (as called from {@see \App\Services\Ai\Agent\Chat\ChatAgent}):
     * ```php
     * $tools = $toolProvider->getTools(
     *     parameterSource: $request->getParameterSource(),
     *     requestedCapabilities: [WellKnownCapabilities::WEB_SEARCH],
     *     requestedTools: ['my_custom_tool'],
     * );
     * $agent->addTool($tools);
     * ```
     * @param ParameterSource $parameterSource
     * @param string[] $requestedCapabilities
     * @param string[] $requestedTools
     * @return Array<ToolInterface|ProviderToolInterface> Neuron-compatible tools to be used for the current request.
     */
    public function getTools(
        ParameterSource $parameterSource,
        array           $requestedCapabilities,
        array           $requestedTools
    ): array
    {
        $tools = [];
        foreach ($this->resolveHawkiToolsToConvert($parameterSource, $requestedCapabilities, $requestedTools) as $tool) {
            if ($tool instanceof ProviderToolInterface) {
                $tools[] = $tool;
            } else if ($tool instanceof AiTool) {
                try {
                    $tools[] = $this->mcpToolConverter->convert($tool);
                } catch (\Throwable $e) {
                    $this->logger->error(sprintf(
                        'Failed to convert tool %s of model %s into Neuron instance: %s',
                        $tool->name,
                        $parameterSource->getModel()->id,
                        $e->getMessage()
                    ), ['exception' => $e]);
                }
            } else {
                $this->logger->warning(sprintf(
                    'Tool %s for model %s is of unsupported type and will be ignored',
                    get_debug_type($tool),
                    $parameterSource->getModel()->id
                ));
            }
        }

        return ToolsResolvedFilterEvent::dispatch($tools, $parameterSource, $requestedCapabilities, $requestedTools)->getTools();
    }

    /**
     * Merges the capability-resolved tools and the explicitly requested tools into one list.
     *
     * Also produces the $noCustomToolsForCapabilities side-channel that tells the second pass
     * which capabilities must not be served by a custom tool (because the rule is NO or NATIVE).
     * @param ParameterSource $parameterSource
     * @param string[] $requestedCapabilities
     * @param string[] $requestedTools
     * @return Array<AiTool|ProviderToolInterface|mixed>
     */
    private function resolveHawkiToolsToConvert(
        ParameterSource $parameterSource,
        array           $requestedCapabilities,
        array           $requestedTools
    ): array
    {
        if (!$parameterSource->getModel()->settings->canUseTools()) {
            return [];
        }

        $noCustomToolsForCapabilities = [];
        return [
            ...$this->buildProviderToolsOfCapabilities($parameterSource, $requestedCapabilities, $noCustomToolsForCapabilities),
            ...$this->buildRequestedTools($parameterSource, $requestedTools, $noCustomToolsForCapabilities)
        ];
    }

    /**
     * Iterates requested capability names and resolves the correct tool for each one based
     * on the model's per-capability rule. Populates $noCustomToolsForCapabilities with any
     * capability name whose rule blocks custom tools (NO or NATIVE), so the requested-tools
     * pass can skip them.
     *
     * @param ParameterSource $parameterSource
     * @param string[] $requestedCapabilities
     * @param string[] $noCustomToolsForCapabilities Passed by reference; capability names
     *        for which custom tools must not be added are appended here.
     * @return array<ProviderToolInterface|AiTool>
     */
    private function buildProviderToolsOfCapabilities(
        ParameterSource $parameterSource,
        array           $requestedCapabilities,
        array           &$noCustomToolsForCapabilities
    ): array
    {
        $model = $parameterSource->getModel();
        $providerAdapter = $this->adapterRegistry->getForProvider($parameterSource->getProvider());
        $capabilityTools = $this->buildModelToolCapabilityMap($model);
        /** @var array<ProviderToolInterface|AiTool> $tools */
        $tools = [];

        foreach ($requestedCapabilities as $capability) {
            if (!$this->capabilityRegistry->has($capability)) {
                $this->logger->warning(sprintf(
                    'Requested capability %s is not registered in the system and will be ignored',
                    $capability
                ));
                continue;
            }

            $rule = $model->capabilities->get($capability);

            // This is simple, "NO" means off, so we skip any tools for this capability and do not add the native capability even if available.
            if ($rule === ModelCapabilityValueType::NO) {
                $noCustomToolsForCapabilities[] = $capability; // If the model has this capability disabled, ignore any tools for this capability.
                continue;
            }

            // For "NATIVE" we want to use the native capability if available, so we ignore any tools for this capability.
            // For "YES" we want to use tools for this capability if available, but if we do not have any tools for this capability, we want to use the native capability
            // if available. So in both cases we want to add the native capability if available.
            if ($rule === ModelCapabilityValueType::NATIVE
                || ($rule === ModelCapabilityValueType::YES && !isset($capabilityTools[$capability]))) {
                // Case 1: We only want to use the native capability, so we ignore any tools for this capability.
                // Case 2: We already checked, that we do not have a tool for this capability, so will not block anything in the next loop
                // and just add the native capability if available.
                $noCustomToolsForCapabilities[] = $capability;
                $providerTool = $providerAdapter->getProviderToolForCapability($capability, $parameterSource);
                if ($providerTool !== null) {
                    $tools[] = $providerTool;
                }
                continue;
            }

            // Here we should always have the case that $rule is YES or TOOL, but in neither case we want to add the native capability if available.
            if (isset($capabilityTools[$capability])) {
                $tools[] = $capabilityTools[$capability];
            }
        }
        return $tools;
    }

    /**
     * @return array<string, AiTool> A map of capability name to the tool that provides it for the given model. If multiple tools of the model provide the same capability,
     * only the last one will be included in the map, allowing overriding tools of a capability.
     */
    private function buildModelToolCapabilityMap(AiModel $model): array
    {
        $capabilityTools = [];

        foreach ($model->tools as $modelTool) {
            $modelCapability = $modelTool->getEffectiveCapability();
            if ($modelCapability === null || !$this->capabilityRegistry->has($modelCapability)) {
                continue;
            }
            // If multiple tools are assigned to the same capability, the last one will be used. This is to allow overriding tools of a capability.
            $capabilityTools[$modelCapability] = $modelTool;
        }

        return $capabilityTools;
    }

    /**
     * Adds explicitly requested tools (by name) to the list, provided they are linked to
     * the model and their capability has not been blocked by the capability pass.
     *
     * @param string[] $requestedTools Tool names matching ai_tools.name.
     * @param string[] $noCustomToolsForCapabilities Capabilities for which custom tools are blocked.
     * @return AiTool[]
     */
    private function buildRequestedTools(
        ParameterSource $parameterSource,
        array           $requestedTools,
        array           $noCustomToolsForCapabilities
    ): array
    {
        $model = $parameterSource->getModel();
        /** @var AiToolCollection $modelTools */
        $modelTools = $model->tools;

        $tools = [];

        foreach ($requestedTools as $requestedTool) {
            if (!$modelTools->hasWithName($requestedTool)) {
                $this->logger->warning(sprintf(
                    'Requested tool %s is not linked to model %s and will be ignored',
                    $requestedTool,
                    $model->model_id
                ));
                continue;
            }

            /** @var AiTool $modelTool - Enforced by "hasWithName" above */
            $modelTool = $modelTools->getWithName($requestedTool);

            if (in_array($modelTool->getEffectiveCapability(), $noCustomToolsForCapabilities, true)) {
                continue; // Skip tools for capabilities that are disabled or native-only.
            }

            $tools[] = $modelTool;
        }

        return $tools;
    }
}
