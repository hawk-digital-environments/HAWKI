<!--
  @component Provider-tools block of the builder's model page: one toggle row per
  registered capability (web search, web fetch, ... — everything except the
  knowledge-base capability, which lives on the builder's Knowledge page).

  Enabling a capability persists a capability transfer string
  (`capability:<key>:<native|auto|<tool-name>>`) on the assistant's
  `providerTools` — unlike the concrete `aiTools` rows, these reference the
  model's native provider tools or any HAWKI tool mapped to the capability, so
  they survive model switches unchanged (mirroring the composer's `ToolMenu`
  semantics; conflicts are surfaced by `ModelToolConflictPanel`).

  Capabilities that can be fulfilled more than one way get a variant picker
  (auto / native / concrete tool), adapted from the composer's `ToolMenuConfig`
  for the builder's `RadioSwitch`/`RadioCard` visual language.

  ## Usage
  ```svelte
  <ProviderToolsList
      capabilities={providerToolCapabilities}
      selected={selectedProviderTools}
      model={currentModel}
      onchange={onProviderToolChange}
      onModeChange={onProviderToolModeChange}
  />
  ```
-->
<script lang="ts">
    import type {AiToolOrCapability} from "$plugins/core/stores/aiToolStoreData.js";
    import type {AiModel} from "$plugins/core/schemas/resources/ai-models.schema.js";
    import {useStore} from "$lib/app/hooks/useStore.svelte.js";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";
    import RadioSwitch from "$plugins/assistants/components/radioSwitch/RadioSwitch.svelte";
    import RadioOption from "$plugins/assistants/components/radioSwitch/RadioOption.svelte";
    import RadioCardGroup from "$lib/components/ui/radio-card/RadioCardGroup.svelte";
    import RadioCard from "$lib/components/ui/radio-card/RadioCard.svelte";
    import InfoPopover from "$lib/components/ui/popover/InfoPopover.svelte";
    import StatusDotForTool from "$plugins/core/modules/chat/components/composer/StatusDotForTool.svelte";

    const modelStore = useStore('ai-models');
    const {__} = useTranslator();

    let {
        /** Capability wrappers to offer, in store order. */
        capabilities,
        /** Capability id → current mode ('native' | 'auto' | concrete tool name); drives toggle + variant state. */
        selected,
        /** The assistant's currently selected model; availability dots stay neutral while unset/null. */
        model = undefined,
        onchange,
        onModeChange,
    } = $props<{
        capabilities: AiToolOrCapability[];
        selected: Map<string, string>;
        model?: AiModel | null | undefined;
        onchange: (capability: AiToolOrCapability, active: boolean) => void;
        onModeChange: (capability: AiToolOrCapability, mode: string) => void;
    }>();

    const byId = $derived(new Map(capabilities.map((c: AiToolOrCapability) => [c.id, c])));
    const selectedIds = $derived([...selected.keys()]);

    // Concrete tools that can fulfill the capability on at least one model —
    // the assistant is model-agnostic, so availability is evaluated across all
    // usable models, like the composer's `ToolMenuConfig`.
    function toolOptionsFor(capability: AiToolOrCapability) {
        if (!capability.is_capability) return [];
        return capability.getTools().filter(t => modelStore.models.some(m => t.isAvailableFor(m)));
    }

    function anyModelHasNative(capability: AiToolOrCapability): boolean {
        return capability.is_capability
            && modelStore.models.some(m => capability.hasNativeCapabilityFor(m));
    }

    // Variant picker only when there is more than one way to fulfill the capability.
    function showsVariants(capability: AiToolOrCapability): boolean {
        return selected.has(capability.id)
            && (toolOptionsFor(capability).length + (anyModelHasNative(capability) ? 1 : 0)) > 1;
    }

    function modeFor(capability: AiToolOrCapability): string {
        return selected.get(capability.id) ?? 'auto';
    }

    // Availability is against the assistant's selected model; without one
    // (nothing chosen yet) the dots stay neutral/positive.
    function supportedForModel(check: (m: AiModel) => boolean): boolean {
        return !model || check(model);
    }

    function handleToggle(value: string, active: boolean) {
        const capability = byId.get(value);
        if (capability) onchange(capability, active);
    }

    function handleModeChange(capability: AiToolOrCapability, mode: string) {
        onModeChange(capability, mode);
    }
</script>

<div class="provider-tools-list">
    <RadioSwitch multiple value={selectedIds} onchange={handleToggle}>
        {#each capabilities as capability (capability.id)}
            <div class="provider-tool">
                <RadioOption
                    value={capability.id}
                    label={capability.displayName}
                    description={capability.description}
                />
                {#if showsVariants(capability)}
                    <div class="provider-tool-variants">
                        <span class="variants-label">{__('assistants.builder.tools.providerTools.variantLabel')}</span>
                        <RadioCardGroup value={modeFor(capability)} onChange={(mode) => handleModeChange(capability, mode)}>
                            <RadioCard value="auto" class="provider-tool-variant">
                                {__('assistants.builder.tools.providerTools.autoLabel')}
                                <span class="variant-meta">
                                    <StatusDotForTool tool={capability} supported={supportedForModel(m => capability.isAvailableFor(m))}/>
                                    <InfoPopover info={__('assistants.builder.tools.providerTools.autoInfo')}/>
                                </span>
                            </RadioCard>
                            {#if anyModelHasNative(capability)}
                                <RadioCard value="native" class="provider-tool-variant">
                                    {__('assistants.builder.tools.providerTools.nativeLabel')}
                                    <span class="variant-meta">
                                        <StatusDotForTool tool={capability} supported={supportedForModel(m => capability.hasNativeCapabilityFor(m))}/>
                                        <InfoPopover info={__('assistants.builder.tools.providerTools.nativeInfo')}/>
                                    </span>
                                </RadioCard>
                            {/if}
                            {#each toolOptionsFor(capability) as option (option.id)}
                                <RadioCard value={option.name} class="provider-tool-variant">
                                    {option.displayName}
                                    <span class="variant-meta">
                                        <StatusDotForTool tool={option} supported={supportedForModel(m => option.isAvailableFor(m))}/>
                                        <InfoPopover info={option.description}/>
                                    </span>
                                </RadioCard>
                            {/each}
                        </RadioCardGroup>
                    </div>
                {/if}
            </div>
        {/each}
    </RadioSwitch>
</div>

<style>
    .provider-tools-list {
        display: flex;
        flex-direction: column;
    }

    .provider-tool {
        display: flex;
        flex-direction: column;
    }

    .provider-tool-variants {
        display: flex;
        flex-direction: column;
        gap: var(--space-1_5, 0.375rem);
        padding: 0 1rem 0.75rem 2.625rem;
    }

    .variants-label {
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        color: var(--color-text-muted);
    }

    .provider-tool-variants :global(.provider-tool-variant .radio-card-body) {
        display: flex;
        justify-content: space-between;
        width: 100%;
    }

    .variant-meta {
        display: flex;
        align-items: center;
        gap: var(--space-2, 0.5rem);
    }
</style>
