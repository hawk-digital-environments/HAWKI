<!--
  @component Warning panel shown when the selected model can't fulfil the currently
  active tools/attachments — driven entirely by `composerContext.modelUsage`
  (`isValid` / `issues`, computed by `ModelUsageSlice` from `tools.active` and
  `attachments.list`). Shows why the model is unsupported (a specific missing-tool name
  when there's exactly one issue of that kind, a generic message otherwise) and lists
  `composerContext.modelUsage.allUsable` as horizontally-scrollable replacement cards;
  clicking one calls `composerContext.model.set(m.id)`.

  Renders nothing when the current model is valid, or when
  `composerContext.guard.showsAiUiElements` is false. Takes no props — it reads
  everything it needs from `ComposerContext`. The model cards are listed in
  `composerContext.modelUsage.allUsable` order (the `AiModelStore`'s own order) —
  there is currently no in-panel sort/tab control.

  ## Usage
  Rendered once by `ChatComposer.svelte`, directly below the textarea so the warning
  appears right where the user is typing:
  ```svelte
  <ComposerTextarea bind:ref={textareaEl} onSend={handleSend}/>
  <ModelConflictPicker/>
  ```
-->
<script lang="ts">
    import {growTransition} from '@hawk-hhg/hawki-svelte-components';
    import Alert02Icon from '@hawk-hhg/hawki-svelte-components/ui/icons/iconset/Alert02Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import type {AiToolOrCapabilityWithState} from '$plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';
    import StatusDotForModel from '$plugins/core/modules/chat/components/composer/StatusDotForModel.svelte';
    import ModelDemandBars from '$plugins/core/modules/chat/components/composer/ModelDemandBars.svelte';

    const composerContext = useComposerContext();
    const {__} = useTranslator();
    const currentModel = $derived(composerContext.model.current);
    const usableModels = $derived(composerContext.modelUsage.allUsable);

    const missingTools: AiToolOrCapabilityWithState[] = $derived.by(() => {
            return composerContext.modelUsage.issues
                .filter(issue => issue.type === 'missing_tools')
                .flatMap(issue => issue.missingTools ?? []);
        }
    );
</script>

{#if !composerContext.modelUsage.isValid && composerContext.guard.showsAiUiElements}
    <div class="chat-conflict-wrapper" transition:growTransition>
        <div class="conflict-container">
            <!-- Header -->
            <div class="conflict-header">
                <div class="conflict-icon-wrapper">
                    <Alert02Icon size={12} class="conflict-icon"/>
                </div>
                <div class="conflict-content">
                    <p class="conflict-title">
                        {#if missingTools.length === 1}
                            {__('chat.composer.modelConflict.conflictTitleSingle', {model: currentModel.label, tool: missingTools[0].displayName})}
                        {:else}
                            {__('chat.composer.modelConflict.conflictTitleMultiple', {model: currentModel.label})}
                        {/if}
                    </p>
                    {#if usableModels.length > 0}
                        <p class="conflict-caps-count">
                            {#if usableModels.length === 1}
                                {__('chat.composer.modelConflict.singleModelCapable')}
                            {:else}
                                {__('chat.composer.modelConflict.multipleModelsCapable', {count: String(usableModels.length)})}
                            {/if}
                        </p>
                    {/if}
                </div>
            </div>

            <!-- Model list -->
            {#if usableModels.length > 0}
                <!-- Scrollable model cards -->
                <div class="conflict-models-scroll">
                    {#each usableModels as m (m.id)}
                        <button
                            onclick={() => composerContext.model.set(m.id)}
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
                    {__('chat.composer.modelConflict.noModelsAvailable')}
                </p>
            {/if}
        </div>
    </div>
{/if}

<style>
    /* ── Container ────────────────────────────────────────────────────── */

    .chat-conflict-wrapper {
        margin-inline: var(--space-2, calc(0.25rem * 2));
        padding-bottom: var(--space-2, calc(0.25rem * 2));
        animation: composer-section-slide-up var(--duration-fast, 300ms) var(--easing-spring) both;
    }

    .conflict-container {
        overflow: hidden;
        border-radius: var(--corner-md);
        background-color: color-mix(in oklch, var(--color-warning) 12%, transparent);
        border: none;
    }

    /* ── Header ───────────────────────────────────────────────────────── */

    .conflict-header {
        display: flex;
        align-items: flex-start;
        gap: calc(0.25rem * 2.5);
        padding-inline: var(--space-3, calc(0.25rem * 3));
        padding-top: var(--space-3, calc(0.25rem * 3));
        padding-bottom: calc(0.25rem * 2.5);
    }

    .conflict-icon-wrapper {
        margin-top: calc(0.25rem * 0.5);
        display: flex;
        height: calc(0.25rem * 5);
        width: calc(0.25rem * 5);
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: var(--corner-xs);
        background-color: color-mix(in oklch, var(--color-warning) 18%, transparent);
        border: none;
    }

    .conflict-content {
        min-width: 0;
        flex: 1;
    }

    .conflict-title {
        margin: 0;
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium, 500);
        line-height: 1.25;
        color: var(--color-text);
    }

    /* ── Cap badges in header ─────────────────────────────────────────── */

    .conflict-caps-count {
        font-size: 10px;
        margin-left: calc(0.25rem * 1);
        color: color-mix(in oklch, var(--color-text) 70%, transparent);
    }

    /* ── Model card scroll ────────────────────────────────────────────── */

    .conflict-models-scroll {
        display: flex;
        gap: calc(0.25rem * 2);
        overflow-x: auto;
        padding-inline: var(--space-3, calc(0.25rem * 3));
        padding-bottom: var(--space-3, calc(0.25rem * 3));
        scrollbar-width: thin;
        scroll-snap-type: x mandatory;
        scroll-padding-inline: var(--space-3, calc(0.25rem * 3));
    }

    /* Trailing inline padding is unreliable on flex scroll containers across
       browsers, so add an explicit end spacer to keep the last card off the edge. */
    .conflict-models-scroll::after {
        content: '';
        flex: 0 0 var(--space-3, calc(0.25rem * 3));
    }

    /* ── Individual model card ────────────────────────────────────────── */

    .conflict-model-card {
        display: flex;
        width: calc(0.25rem * 44);
        flex-shrink: 0;
        scroll-snap-align: start;
        flex-direction: column;
        gap: calc(0.25rem * 1.5);
        border-radius: var(--corner-sm);
        border: none;
        background: none;
        background-color: var(--color-surface-raised);
        padding: calc(0.25rem * 2.5);
        text-align: left;
        cursor: pointer;
        transition: box-shadow var(--duration-fast, 150ms) var(--easing-default);

        &:hover {
            box-shadow: var(--elevation-1);
        }
    }

    .conflict-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: calc(0.25rem * 2);
    }

    .conflict-provider-row {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: calc(0.25rem * 1.5);
    }

    .conflict-provider-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 10px;
        font-weight: var(--font-weight-medium, 500);
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--color-text-muted);
    }

    .conflict-card-right {
        display: flex;
        align-items: center;
        gap: calc(0.25rem * 1.5);
        flex-shrink: 0;
    }

    .conflict-model-name {
        font-size: 13px;
        font-weight: var(--font-weight-medium, 500);
        line-height: 1.25;
        color: var(--color-text);
    }

    /* ── No-models fallback ───────────────────────────────────────────── */

    .conflict-no-models {
        font-size: 11px;
        color: color-mix(in oklch, var(--color-text) 90%, transparent);
        padding-inline: var(--space-3, calc(0.25rem * 3));
        padding-bottom: var(--space-3, calc(0.25rem * 3));
        margin: 0;
    }
</style>
