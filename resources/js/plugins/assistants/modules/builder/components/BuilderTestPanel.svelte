<!--
  @component Floating test chat. Collapsed it IS the chat button: the same
  element morphs between the round launcher and the chat card. Stays mounted
  across every builder step (it lives in the builder layout), so a running
  conversation survives step changes and always reflects the live `draft`.
-->
<script lang="ts">
    import Chatbox from '$plugins/assistants/components/testChat';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import BubbleChatIcon from '$lib/components/ui/icons/iconset/BubbleChatIcon.svelte';
    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';
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

<div class="test-shell" class:open>
<aside class="test-panel" class:open aria-label={__('assistants.builder.test.title')}>
    <button type="button" class="launcher" inert={open}
            aria-label={__('assistants.builder.test.open')}
            title={__('assistants.builder.test.open')}
            onclick={() => open = true}>
        <BubbleChatIcon/>
    </button>
    <div class="inner" inert={!open}>
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
                    <ButtonWithTooltip variant="iconGhost" iconLeft={Cancel01Icon}
                                       tooltip={__('assistants.builder.test.close')}
                                       onclick={() => open = false}/>
                </header>
            {/snippet}
        </Chatbox>
    </div>
</aside>
</div>

<style>
    /* One surface, two shapes. The shell reserves the card's final box; the
       panel's real width, height and radius animate between the round
       launcher and that box, anchored bottom-right, on a spring. The chat
       inside is laid out at the final size the whole time (container units),
       so the surface grows over it instead of reflowing it. The blue
       launcher layer is the surface's colour, not separate content. */
    .test-shell {
        --spring: linear(0, 0.034, 0.116, 0.224, 0.341, 0.456, 0.563, 0.658, 0.739, 0.807, 0.862, 0.905, 0.938, 0.963, 0.981, 0.994, 1.002, 1.007, 1.01, 1.011, 1.011, 1.01, 1.009, 1.008, 1.007, 1.005, 1.004, 1.003, 1.003, 1.002, 1.001, 1.001, 1.001, 1, 1, 1, 1);
        --settle: cubic-bezier(0.3, 0, 0.2, 1);
        --fab: 3rem;
        --card-radius: 1.5rem;
        position: absolute;
        right: var(--test-fab-inset);
        bottom: var(--test-fab-inset);
        z-index: 3;
        width: min(var(--test-panel-w), calc(100% - 2 * var(--test-fab-inset)));
        height: min(40rem, calc(100% - 2 * var(--test-fab-inset)));
        container-type: size;
        pointer-events: none;
    }

    .test-panel {
        position: absolute;
        right: 0;
        bottom: 0;
        width: var(--fab);
        height: var(--fab);
        overflow: hidden;
        /* Same radius as the card (the circle is 3rem, so 1.5rem is round):
           corners stay continuous through the whole morph. */
        border-radius: var(--card-radius);
        background: var(--color-bg);
        box-shadow: 0 6px 16px oklch(0% 0 0 / 0.18);
        pointer-events: auto;
        /* Close: one smooth pull back into the circle; height leads by a
           hair so it reads as folding, not as an L-shaped wipe. */
        transition:
            height 340ms var(--settle),
            width 360ms var(--settle) 30ms,
            box-shadow 360ms var(--settle);
    }

    .test-panel.open {
        width: 100cqw;
        height: 100cqh;
        box-shadow: 0 0 0 1px oklch(0% 0 0 / 0.06), 0 18px 40px oklch(0% 0 0 / 0.14);
        /* Open: stiff spring with a barely-there settle; width leads height
           slightly so the circle swells out of its corner. */
        transition:
            width 520ms var(--spring),
            height 560ms var(--spring) 35ms,
            box-shadow 450ms var(--settle);
    }

    .launcher {
        position: absolute;
        inset: 0;
        z-index: 1;
        display: grid;
        place-items: end;
        padding: 0;
        border: none;
        background: var(--color-accent-fill);
        color: var(--color-on-interactive);
        cursor: pointer;
        /* Blue returns only as the surface nears the circle. */
        transition: opacity 160ms var(--settle) 190ms, background-color 150ms;
    }

    /* Same dark-mode foreground as the accent Button (Button.svelte). */
    :global(html.darkMode) .launcher {
        color: var(--color-active-text);
    }

    :global(html.darkMode) .launcher:focus-visible {
        outline-color: var(--color-active-text);
    }

    /* The icon stays pinned in the corner the button came from. */
    .launcher :global(svg) {
        width: 1.375rem;
        height: 1.375rem;
        margin: calc((var(--fab) - 1.375rem) / 2);
    }

    .launcher:hover {
        background: var(--color-accent-fill-hover);
    }

    .launcher:focus-visible {
        outline: 2px solid var(--color-on-interactive);
        outline-offset: -4px;
        border-radius: inherit;
    }

    .open .launcher {
        opacity: 0;
        pointer-events: none;
        /* Blue drains in the first moments so the card surface takes over
           while it grows. */
        transition: opacity 140ms linear 40ms;
    }

    .inner {
        position: absolute;
        right: 0;
        bottom: 0;
        width: 100cqw;
        height: 100cqh;
    }

    /* Wide viewports: open = docked column. The shell spans the full right
       edge; the panel slides its corner offset to 0 and squares off while
       it grows, so the circle morphs straight into the column. */
    @media (--bp-xl) {
        .test-shell {
            top: 0;
            right: 0;
            bottom: 0;
            width: var(--test-panel-w);
            height: auto;
        }

        .test-panel {
            right: var(--test-fab-inset);
            bottom: var(--test-fab-inset);
            transition:
                height 340ms var(--settle),
                width 360ms var(--settle) 30ms,
                right 360ms var(--settle) 30ms,
                bottom 340ms var(--settle),
                border-radius 300ms var(--settle) 60ms,
                box-shadow 360ms var(--settle);
        }

        .test-panel.open {
            right: 0;
            bottom: 0;
            border-radius: 0;
            box-shadow: -1px 0 0 var(--color-border);
            transition:
                width 480ms var(--settle),
                height 560ms var(--spring) 35ms,
                right 480ms var(--settle),
                bottom 480ms var(--settle),
                border-radius 360ms var(--settle) 120ms,
                box-shadow 360ms var(--settle);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .test-panel, .test-panel.open, .launcher, .open .launcher {
            transition: none !important;
        }
    }

    .panel-header {
        display: flex;
        align-items: center;
        gap: var(--space-1);
        /* Mirrors the composer at the bottom (see ChatInput.svelte): same
           outer inset on top and right as the composer box has at the
           bottom and sides, title aligned with the composer's text. */
        padding: var(--space-4) var(--space-4) 0 calc(var(--space-4) + var(--space-5));
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

    /* Mobile: takes over the whole builder area. */
    @media (--bp-md-and-smaller) {
        .test-shell {
            inset: 0;
            width: auto;
            height: auto;
        }

        .test-panel {
            right: var(--test-fab-inset);
            bottom: var(--test-fab-inset);
        }

        .test-panel.open {
            right: 0;
            bottom: 0;
            border-radius: 0;
        }
    }
</style>
