<!--
  @component Collapsible right-hand test chat of the builder. Stays mounted
  across every builder step (it lives in the builder layout), so a running
  conversation survives step changes and always reflects the live `draft`.
-->
<script lang="ts">
    import Chatbox from '$plugins/assistants/components/testChat';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import PanelRightCloseIcon from '$lib/components/ui/icons/iconset/PanelRightCloseIcon.svelte';
    import RefreshIcon from '$lib/components/ui/icons/iconset/RefreshIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useBuilderContext} from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';

    interface Props {
        open: boolean;
    }

    let {open = $bindable()}: Props = $props();

    const {__} = useTranslator();
    const builder = useBuilderContext();
</script>

<aside class="test-panel" class:open inert={!open} aria-label={__('assistants.builder.test.title')}>
    <div class="inner">
        <Chatbox assistant={builder.draft}>
            {#snippet header(chat)}
                <header class="panel-header">
                    <h3 class="panel-title">{__('assistants.builder.test.title')}</h3>
                    <p class="u-sr-only">{__('assistants.builder.test.description')}</p>
                    {#if chat.messages.length > 0}
                        <ButtonWithTooltip variant="iconGhost" iconLeft={RefreshIcon}
                                           tooltip={__('assistants.builder.test.reset')}
                                           disabled={chat.status === 'streaming'}
                                           onclick={() => chat.clear()}/>
                    {/if}
                    <ButtonWithTooltip variant="iconGhost" iconLeft={PanelRightCloseIcon}
                                       tooltip={__('assistants.builder.test.close')}
                                       onclick={() => open = false}/>
                </header>
            {/snippet}
        </Chatbox>
    </div>
</aside>

<style>
    .test-panel {
        grid-area: test;
        min-height: 0;
        overflow: hidden;
        border-left: var(--divider);
        /* Mirrors the left nav panel so both sides read as chrome. */
        background: var(--panel-bg);
    }

    .test-panel:not(.open) {
        visibility: hidden;
    }

    .inner {
        width: var(--test-panel-w);
        height: 100%;
    }

    .panel-header {
        display: flex;
        align-items: center;
        gap: var(--space-1);
        /* Title lines up with the composer's text, the icons with its send
           button's centre (see ChatInput.svelte). */
        padding: var(--space-3) calc(var(--space-4) + var(--space-1_5) + 0.5rem) 0 calc(var(--space-4) + var(--space-5));
    }

    .panel-title {
        margin: 0 auto 0 0;
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }

    /* The panel is the frame; drop the chatbox's own card chrome. */
    .inner :global(.chatbox) {
        border: none;
        border-radius: 0;
        background: transparent;
    }

    /* Mobile: overlays the builder instead of taking a column. */
    @media (--bp-md-and-smaller) {
        .test-panel {
            position: absolute;
            inset: 0;
            z-index: 2;
            border-left: none;
            background: var(--color-bg);
        }

        .inner {
            width: 100%;
        }
    }
</style>
