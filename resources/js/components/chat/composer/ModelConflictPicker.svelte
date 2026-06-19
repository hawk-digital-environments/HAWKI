<!--
  @component Warning panel shown when the selected model lacks capabilities required
  by active tools or file attachments. Lists compatible replacement models with tabs
  for sorting by recommendation, cost, or capability tier.
-->
<script lang="ts">
    import {TriangleAlert} from '@lucide/svelte';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import ModelDemandBars from '$lib/components/chat/composer/ModelDemandBars.svelte';
    import type {AiTool} from '$lib/schemas/resources/ai-tools.schema.js';
    import {toolDisplayName} from '$lib/utils/aiToolUtils.js';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import StatusDotForModel from '$lib/components/chat/composer/StatusDotForModel.svelte';

    const composerContext = useComposerContext();
    const currentModel = $derived(composerContext.model.current);
    const usableModels = $derived(composerContext.modelUsage.allUsable);

    const missingTools: AiTool[] = $derived.by(() => {
            return composerContext.modelUsage.issues
                .filter(issue => issue.type === 'missing_tools')
                .flatMap(issue => issue.missingTools ?? []);
        }
    );
</script>

{#if !composerContext.modelUsage.isValid}
    <div class="chat-conflict-wrapper" transition:growTransition>
        <div class="conflict-container">
            <!-- Header -->
            <div class="conflict-header">
                <div class="conflict-icon-wrapper">
                    <TriangleAlert size={12} class="conflict-icon"/>
                </div>
                <div class="conflict-content">
                    <p class="conflict-title">
                        {currentModel.label} unterstützt
                        {#if missingTools.length === 1}
                            {toolDisplayName(missingTools[0])}
                        {:else}
                            die folgenden Anforderungen
                        {/if}
                        nicht
                    </p>
                    {#if usableModels.length > 0}
                        <p class="conflict-caps-count">
                            {usableModels.length} Modell{usableModels.length === 1 ? '' : 'e'} können alles
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
                    Kein verfügbares Modell unterstützt diese Kombination. Entferne ein Tool oder einen Anhang.
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
