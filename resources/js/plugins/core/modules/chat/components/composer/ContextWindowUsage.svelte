<!--
  @component Small ring indicator next to the composer's model selector showing how full the
  selected model's context window already is. The ring fills with the estimated share of the
  input limit the next request will carry (see `contextWindowUsage.ts`); a tooltip spells out
  the exact token counts. The color escalates from muted over `--color-warning` to
  `--color-error` as the conversation approaches the limit.

  Renders nothing in room chats, while the AI UI is hidden, or when the model reports no input
  limit — in those cases there is nothing meaningful to show.

  Takes no props: the conversation comes from the `chat` store, the model from the composer
  context.

  ## Usage
  Placed in the composer's top row, right after the model picker, so the limit sits next to the
  model it belongs to:
  ```svelte
  <ModelPicker/>
  <ContextWindowUsage/>
  ```
-->
<script lang="ts">
    import RadialProgress from '$lib/components/ui/radial-progress/RadialProgress.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {estimateContextWindowUsage} from '$plugins/core/modules/chat/utils/contextWindowUsage.js';
    import {formatTokenCount, getModelLimits} from '$plugins/core/components/modelInsights.js';

    const composerContext = useComposerContext();
    const chatStore = useStore('chat');
    const app = useApp();
    const {__} = useTranslator();

    const htmlLang = $derived(app.localization.locale.htmlLang);

    const usage = $derived(estimateContextWindowUsage(
        chatStore.active?.messages ?? [],
        getModelLimits(composerContext.model.current)?.max_input_tokens
    ));

    const visible = $derived(
        composerContext.type === 'aiConv' && composerContext.guard.showsAiUiElements && usage !== null
    );

    const tooltipText = $derived(usage === null ? '' : __('chat.composer.contextUsage.tooltip', {
        used: formatTokenCount(usage.usedTokens, htmlLang),
        max: formatTokenCount(usage.maxTokens, htmlLang),
        percent: String(usage.percent)
    }));

    const ariaLabel = $derived(usage === null ? '' : __('chat.composer.contextUsage.ariaLabel', {
        percent: String(usage.percent)
    }));
</script>

{#if visible && usage !== null}
    <Tooltip tooltip={tooltipText} delayDuration={300} hiddenLabel={ariaLabel}>
        {#snippet children({props})}
            <span
                class="context-window-usage context-window-usage--{usage.level}"
                transition:growTransition={{mode: 'horizontal'}}
                {...props}
            >
                <RadialProgress value={usage.percent} size={16} strokeWidth={2} aria-label={ariaLabel}/>
            </span>
        {/snippet}
    </Tooltip>
{/if}

<style>
    .context-window-usage {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 2rem;
        width: 2rem;
        flex-shrink: 0;
        border-radius: var(--corner-full);
        color: var(--color-text-muted);
        cursor: default;

        &:focus-visible {
            outline: 2px solid var(--color-focus-ring, var(--color-interactive));
            outline-offset: 2px;
        }
    }

    /* ── Fill levels ─────────────────────────────────────────────────── */

    .context-window-usage--warning {
        color: var(--color-warning);
    }

    .context-window-usage--critical {
        color: var(--color-error);
    }
</style>
