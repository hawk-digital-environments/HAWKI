<!--
  @component Tooltip that appears on hover or keyboard focus. On touch devices
  a 300 ms long-press opens the tooltip (and the context menu is suppressed so
  the two don't clash). The trigger is provided via the `children` snippet;
  tooltip content via the `tooltip` prop (string or snippet).
-->
<script lang="ts">
    import {mergeProps, Tooltip as TooltipPrimitive, type TooltipContentProps} from 'bits-ui';
    import type {Snippet} from 'svelte';
    import SnippetOrString from '$lib/components/util/snippetOrString/SnippetOrString.svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import SnippetOrStringTrigger from '$lib/components/util/snippetOrString/SnippetOrStringTrigger.svelte';

    type Props = Omit<HTMLAttributes<HTMLDivElement>, 'children'> & Partial<{
        /** Delay in milliseconds before the tooltip is shown after hovering over the trigger. Default is 200ms. */
        delayDuration?: number;
        /** The content to display inside the tooltip. Can be a string or a Svelte snippet. */
        tooltip: Snippet | string;
        /** Preferred side relative to the trigger. */
        side?: 'top' | 'right' | 'bottom' | 'left';
        /** Offset in pixels from the trigger. */
        sideOffset?: number;
        /** Can be used to force the tooltip to be open or closed. If not provided, the tooltip will open on hover/focus and close on blur/mouse leave. */
        open?: boolean;
        /** If true, the tooltip will not open on hover/focus. */
        disabled?: boolean;
        /**
         * The content that triggers the tooltip, typically an icon or button. Can be a string or a Svelte snippet.
         * If a snippet is provided, it will receive a `props` object as an argument, which MUST be used to spread onto the root element of the snippet.
         * This ensures proper functionality of the tooltip trigger.
         */
        children?: Snippet<[{ props: Record<string, any> }]> | Snippet | string;
    }>;

    let {
        delayDuration = 1000,
        tooltip,
        children,
        side = 'top',
        sideOffset = 4,
        open = $bindable(false),
        disabled,
        ...restProps
    }: Props = $props();

    const longPress = $state.raw({
        timer: null as ReturnType<typeof setTimeout> | null
    });

    let isTouching = false;

    function handleTouchStart(e: TouchEvent) {
        isTouching = true;
        longPress.timer = setTimeout(() => {
            open = true;
        }, 300);
    }

    function handleTouchEnd() {
        isTouching = false;
        if (longPress.timer) {
            clearTimeout(longPress.timer);
            longPress.timer = null;
        }
    }

    function handleContextMenu(e: Event) {
        // We only want to supress the context menu if the user is long-pressing to open the tooltip.
        if (isTouching) {
            e.preventDefault();
        }
    }

</script>

<TooltipPrimitive.Provider>
    <TooltipPrimitive.Root
        {delayDuration}
        {open}
        {disabled}
        onOpenChange={o => open = o}
        ignoreNonKeyboardFocus>
        <TooltipPrimitive.Trigger>
            {#snippet child(a)}
                <SnippetOrStringTrigger value={children as string|Snippet} snippetArgs={{
                    props: mergeProps(
                        a.props,
                        {
                            ontouchstart: handleTouchStart,
                            ontouchend: handleTouchEnd,
                            ontouchcancel: handleTouchEnd,
                            oncontextmenu: handleContextMenu
                        }
                    )
                }}/>
            {/snippet}
        </TooltipPrimitive.Trigger>
        <TooltipPrimitive.Portal>
            <TooltipPrimitive.Content
                {...mergeProps({class: 'tooltip-content', side, sideOffset}, restProps) as TooltipContentProps}
            >
                <SnippetOrString value={tooltip}/>
            </TooltipPrimitive.Content>
        </TooltipPrimitive.Portal>
    </TooltipPrimitive.Root>
</TooltipPrimitive.Provider>

<style>
    :global(.tooltip-content) {
        --tooltip-bg: var(--color-surface-raised);
        --tooltip-text: var(--color-text);

        position: relative;
        border-radius: var(--corner-sm);
        border: var(--border);
        padding-inline: var(--space-3);
        padding-block: var(--space-1);
        font-size: var(--font-size-xxs);
        line-height: var(--line-height-normal);
        background-color: var(--tooltip-bg);
        color: var(--tooltip-text);
        box-shadow: var(--elevation-1);
        z-index: 100;
        max-width: 300px;

        &[data-state="delayed-open"],
        &[data-state="instant-open"] {
            animation: tooltip-in 100ms var(--easing-default, ease);
        }

        &[data-state="closed"] {
            animation: tooltip-out 100ms var(--easing-default, ease);
        }
    }

    @keyframes tooltip-in {
        from {
            opacity: 0;
            scale: 0.96;
        }
        to {
            opacity: 1;
            scale: 1;
        }
    }

    @keyframes tooltip-out {
        from {
            opacity: 1;
            scale: 1;
        }
        to {
            opacity: 0;
            scale: 0.96;
        }
    }
</style>
