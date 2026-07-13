<!--
  @component Warning panel shown when the builder's selected model can't
  fulfil the currently enabled tools — the same check the composer runs in
  `ModelUsageSlice` (`isValid` from `tools.active` vs. the model), adapted for
  the assistant builder's `Assistant.model` / `Assistant.aiTools` instead of
  a live chat's `ModelSlice` / `ToolSlice`. Shows why the model is unsupported
  (the specific missing tool when there's exactly one, a generic message
  otherwise) and lists compatible models as clickable replacement cards —
  picking one calls `builder.setModel(m.id)`, mirroring
  `ModelConflictPicker`'s `composerContext.model.set(m.id)`.

  Renders nothing when no model is selected yet, no tools are enabled, or the
  selected model already supports every enabled tool.

  ## Usage
  ```svelte
  <ModelSelector onchange={(modelId) => builder.setModel(modelId)} />
  <ModelToolConflictPanel/>
  ```
-->
<script lang="ts">
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import Alert02Icon from '$lib/components/ui/icons/iconset/Alert02Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useBuilderContext} from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import {isAiToolAvailableFor} from '$plugins/core/stores/aiToolStoreData.js';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';
    import StatusDotForModel from '$plugins/core/modules/chat/components/composer/StatusDotForModel.svelte';
    import ModelDemandBars from '$plugins/core/modules/chat/components/composer/ModelDemandBars.svelte';

    const builder = useBuilderContext();
    const modelStore = useStore('ai-models');
    const {__} = useTranslator();

    // `Assistant.aiTools` is persisted to sessionStorage between builder
    // sessions, so it can't be relied on to still carry the `AiToolOrCapability`
    // wrapper's `isAvailableFor` method — `isAiToolAvailableFor` only needs the
    // plain `id`/`status` fields that survive that round-trip.
    const activeTools = $derived(builder.draft.aiTools ?? []);

    const currentModel = $derived(
        modelStore.models.find(m => m.id === builder.draft.model) ?? null
    );

    function isModelUsable(model: AiModel): boolean {
        if (activeTools.length === 0) return true;
        if (!model.settings?.tool_calling) return false;
        return activeTools.every(tool => isAiToolAvailableFor(tool, model));
    }

    const missingTools = $derived(
        currentModel ? activeTools.filter(tool => !isAiToolAvailableFor(tool, currentModel)) : []
    );

    const isValid = $derived(!currentModel || isModelUsable(currentModel));

    const usableModels = $derived(modelStore.models.filter(isModelUsable));
</script>

{#if currentModel && !isValid}
    <div class="model-conflict-wrapper" transition:growTransition>
        <div class="conflict-container">
            <div class="conflict-header">
                <div class="conflict-icon-wrapper">
                    <Alert02Icon size={12} class="conflict-icon"/>
                </div>
                <div class="conflict-content">
                    <p class="conflict-title">
                        {#if missingTools.length === 1}
                            {__('assistants.builder.model.conflict.titleSingle', {model: currentModel.label, tool: missingTools[0].name})}
                        {:else}
                            {__('assistants.builder.model.conflict.titleMultiple', {model: currentModel.label})}
                        {/if}
                    </p>
                    {#if usableModels.length > 0}
                        <p class="conflict-caps-count">
                            {#if usableModels.length === 1}
                                {__('assistants.builder.model.conflict.singleModelCapable')}
                            {:else}
                                {__('assistants.builder.model.conflict.multipleModelsCapable', {count: String(usableModels.length)})}
                            {/if}
                        </p>
                    {/if}
                </div>
            </div>

            {#if usableModels.length > 0}
                <div class="conflict-models-scroll">
                    {#each usableModels as m (m.id)}
                        <button
                            onclick={() => builder.setModel(m.id)}
                            class="conflict-model-card"
                        >
                            <div class="conflict-card-top">
                                <div class="conflict-provider-row">
                                    <StatusDotForModel model={m}/>
                                    <span class="conflict-provider-name">{m.provider?.name}</span>
                                </div>
                                <div class="conflict-card-right">
                                    <ModelDemandBars model={m}/>
                                </div>
                            </div>
                            <span class="conflict-model-name">{m.label}</span>
                        </button>
                    {/each}
                </div>
            {:else}
                <p class="conflict-no-models">
                    {__('assistants.builder.model.conflict.noModelsAvailable')}
                </p>
            {/if}
        </div>
    </div>
{/if}

<style>
    /* Same visual language as `ModelConflictPicker` (the composer's equivalent
       panel) — kept as its own copy rather than a shared component since the
       two live in different plugins and read from different contexts. */

    .model-conflict-wrapper {
        animation: builder-conflict-slide-up var(--duration-fast) var(--easing-spring) both;
    }

    @keyframes builder-conflict-slide-up {
        from {
            opacity: 0;
            transform: translateY(-4px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .conflict-container {
        overflow: hidden;
        border-radius: var(--corner-md);
        background-color: color-mix(in oklch, var(--color-warning) 12%, transparent);
    }

    .conflict-header {
        display: flex;
        align-items: flex-start;
        gap: var(--space-2_5);
        padding-inline: var(--space-3);
        padding-top: var(--space-3);
        padding-bottom: var(--space-2_5);
    }

    .conflict-icon-wrapper {
        margin-top: calc(var(--space-0_5) * 0.5);
        display: flex;
        height: var(--space-5);
        width: var(--space-5);
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: var(--corner-xs);
        background-color: color-mix(in oklch, var(--color-warning) 18%, transparent);
    }

    .conflict-content {
        min-width: 0;
        flex: 1;
    }

    .conflict-title {
        margin: 0;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        line-height: var(--line-height-tight);
        color: var(--color-text);
    }

    .conflict-caps-count {
        font-size: var(--font-size-xxs);
        margin-left: var(--space-1);
        color: var(--color-text-muted);
    }

    .conflict-models-scroll {
        display: flex;
        gap: var(--space-2);
        overflow-x: auto;
        padding-inline: var(--space-3);
        padding-bottom: var(--space-3);
        scrollbar-width: thin;
        scroll-snap-type: x mandatory;
        scroll-padding-inline: var(--space-3);
    }

    .conflict-models-scroll::after {
        content: '';
        flex: 0 0 var(--space-3);
    }

    .conflict-model-card {
        display: flex;
        width: 11rem;
        flex-shrink: 0;
        scroll-snap-align: start;
        flex-direction: column;
        gap: var(--space-1_5);
        border-radius: var(--corner-sm);
        border: none;
        background-color: var(--color-surface-raised);
        padding: var(--space-2_5);
        text-align: left;
        cursor: pointer;
        transition: box-shadow var(--duration-fast) var(--easing-default);
    }

    .conflict-model-card:hover {
        box-shadow: var(--elevation-1);
    }

    .conflict-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2);
    }

    .conflict-provider-row {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: var(--space-1_5);
    }

    .conflict-provider-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium);
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--color-text-muted);
    }

    .conflict-card-right {
        display: flex;
        align-items: center;
        gap: var(--space-1_5);
        flex-shrink: 0;
    }

    .conflict-model-name {
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        line-height: var(--line-height-tight);
        color: var(--color-text);
    }

    .conflict-no-models {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        padding-inline: var(--space-3);
        padding-bottom: var(--space-3);
        margin: 0;
    }
</style>
