<!--
  @component Capabilities block of the builder's model page: one toggle row per
  registered capability (web search, web fetch, ... — everything except the
  knowledge-base capability, which lives on the builder's Knowledge page).

  Enabling a capability persists a capability transfer string
  (`capability:<key>:<native|auto|<tool-name>>`) on the assistant's
  `capabilities` — unlike the concrete `aiTools` rows, these reference the
  model's native provider tools or any HAWKI tool mapped to the capability, so
  they survive model switches unchanged (mirroring the composer's `ToolMenu`
  semantics; conflicts are surfaced by `ModelToolConflictPanel`).

  Capabilities that can be fulfilled more than one way get a variant picker
  (auto / native / concrete tool), adapted from the composer's `ToolMenuConfig`
  for the builder's `RadioSwitch`/`RadioCard` visual language.

  ## Usage
  ```svelte
  <CapabilitiesList
      capabilities={capabilityEntries}
      selected={selectedCapabilities}
      model={currentModel}
      onchange={onCapabilityChange}
      onModeChange={onCapabilityModeChange}
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
    import VariantCard from "$plugins/assistants/modules/builder/components/aiToolComponents/VariantCard.svelte";
    import ToolIcon from "$plugins/core/modules/chat/components/composer/utils/ToolIcon.svelte";
    import ToolStatusDot from "$plugins/assistants/modules/builder/components/aiToolComponents/ToolStatusDot.svelte";

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
    // (nothing chosen yet) the dots stay neutral/positive and every variant
    // stays enabled. Drives both each variant card's status dot and its
    // disabled state, so the two can never disagree: an unavailable variant
    // (e.g. "Native tool" on a model without the native capability) is
    // visibly disabled instead of silently reverting via the prune effect.
    function supportedForModel(check: (m: AiModel) => boolean): boolean {
        return !model || check(model);
    }

    // A capability the selected model can't fulfil is disabled (with a
    // warning dot) — the parent only renders such rows when the user had
    // selected them earlier, so the row explains itself instead of silently
    // vanishing. It can never be toggled on, so unavailable selections can't
    // enter the draft (or the save).
    function isDisabled(capability: AiToolOrCapability): boolean {
        return !!model && !capability.isAvailableFor(model);
    }

    function handleToggle(value: string, active: boolean) {
        const capability = byId.get(value);
        if (capability) onchange(capability, active);
    }

    function handleModeChange(capability: AiToolOrCapability, mode: string) {
        onModeChange(capability, mode);
    }
</script>

<div class="capabilities-list">
    <RadioSwitch multiple value={selectedIds} onchange={handleToggle}>
        {#each capabilities as capability (capability.id)}
            <div class="capability">
                <RadioOption
                    value={capability.id}
                    label={capability.displayName}
                    description={capability.description}
                    disabled={isDisabled(capability)}
                >
                    {#snippet leading()}
                        <ToolIcon tool={capability} size={20}/>
                    {/snippet}
                    {#snippet meta()}
                        <ToolStatusDot
                            tool={capability}
                            supported={!isDisabled(capability)}
                            {model}
                        />
                    {/snippet}
                </RadioOption>
                {#if showsVariants(capability)}
                    <div class="capability-variants">
                        <span class="variants-label">{__('assistants.builder.tools.capabilities.variantLabel')}</span>
                        <RadioCardGroup value={modeFor(capability)} onChange={(mode) => handleModeChange(capability, mode)}>
                            <VariantCard
                                value="auto"
                                disabled={!supportedForModel(m => capability.isAvailableFor(m))}
                                disabledTooltip={__('assistants.builder.tools.capabilities.variantUnavailable')}
                                {model}
                                statusTool={capability}
                                statusSupported={supportedForModel(m => capability.isAvailableFor(m))}
                                info={__('assistants.builder.tools.capabilities.autoInfo')}
                            >
                                {__('assistants.builder.tools.capabilities.autoLabel')}
                            </VariantCard>
                            {#if anyModelHasNative(capability)}
                                <VariantCard
                                    value="native"
                                    disabled={!supportedForModel(m => capability.hasNativeCapabilityFor(m))}
                                    disabledTooltip={__('assistants.builder.tools.capabilities.variantUnavailable')}
                                    {model}
                                    statusTool={capability}
                                    statusSupported={supportedForModel(m => capability.hasNativeCapabilityFor(m))}
                                    info={__('assistants.builder.tools.capabilities.nativeInfo')}
                                >
                                    {__('assistants.builder.tools.capabilities.nativeLabel')}
                                </VariantCard>
                            {/if}
                            {#each toolOptionsFor(capability) as option (option.id)}
                                <VariantCard
                                    value={option.name}
                                    disabled={!supportedForModel(m => option.isAvailableFor(m))}
                                    disabledTooltip={__('assistants.builder.tools.capabilities.variantUnavailable')}
                                    {model}
                                    statusTool={option}
                                    statusSupported={supportedForModel(m => option.isAvailableFor(m))}
                                    info={option.description}
                                >
                                    {option.displayName}
                                </VariantCard>
                            {/each}
                        </RadioCardGroup>
                    </div>
                {/if}
            </div>
        {/each}
    </RadioSwitch>
</div>

<style>
    .capabilities-list {
        display: flex;
        flex-direction: column;
    }

    .capability {
        display: flex;
        flex-direction: column;
    }

    .capability-variants {
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

    .capability-variants :global(.capability-variant .radio-card-body) {
        display: flex;
        justify-content: space-between;
        width: 100%;
    }
</style>
