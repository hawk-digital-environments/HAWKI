<script lang="ts">
    import {mergeProps, Popover as PopoverPrimitive, type PopoverContentProps} from 'bits-ui';
    import type {Snippet} from 'svelte';
    import SnippetOrString from '$lib/components/util/snippetOrString/SnippetOrString.svelte';
    import SnippetOrStringTrigger from '$lib/components/util/snippetOrString/SnippetOrStringTrigger.svelte';

    interface Props {
        /** Whether the popover is open. Supports bind:open. */
        open?: boolean;
        /** If true, the popover will open when the trigger is hovered. */
        openOnHover?: boolean;
        /** An optional group name. Popovers with the same group will ensure that only one popover in the group is open at a time. */
        group?: string;
        /** Preferred side relative to the trigger. */
        side?: 'top' | 'right' | 'bottom' | 'left';
        /** Alignment relative to the trigger. */
        align?: 'start' | 'center' | 'end';
        /** Pixel offset from the trigger. */
        sideOffset?: number;
        /**
         * The content that triggers the popover, typically an icon or button. Can be a string or a Svelte snippet.
         * If a snippet is provided, it will receive a `props` object as an argument, which MUST be used to spread onto the root element of the snippet.
         * This ensures proper functionality of the popover trigger.
         */
        children?: Snippet<[{ props: Record<string, any> } & Record<string, any>]> | Snippet | string;
        /** The content to display inside the popover. Can be a string or a Svelte snippet. */
        popover?: Snippet | string;
        /** Additional props to pass to the Popover.Content component, such as custom styles or class names. */
        contentProps?: PopoverContentProps;
    }

    let {
        group,
        open = $bindable(false),
        openOnHover,
        side = 'top',
        align = 'start',
        sideOffset = 4,
        popover,
        children,
        contentProps
    }: Props = $props();

    const popoverId = $props.id();

    // This effect ensures, that when a popover is opened, all other popovers in the same group are closed.
    // This is true for clicking and hover interactions.
    $effect(() => {
        if (!open || !group) {
            return;
        }

        window.dispatchEvent(new CustomEvent('popover-open', {detail: {id: popoverId, group}}));

        function onOtherPopoverOpen(e: CustomEvent) {
            if (e.detail.id !== popoverId && e.detail.group === group && open) {
                open = false;
            }
        }

        window.addEventListener('popover-open', onOtherPopoverOpen as EventListener);
        return () => window.removeEventListener('popover-open', onOtherPopoverOpen as EventListener);
    });
</script>

<PopoverPrimitive.Root bind:open={open}>
    <PopoverPrimitive.Trigger openOnHover={openOnHover}>
        {#snippet child(a)}
            <SnippetOrStringTrigger value={children} snippetArgs={a}/>
        {/snippet}
    </PopoverPrimitive.Trigger>
    <PopoverPrimitive.Portal>
        <PopoverPrimitive.Content
            {...mergeProps({side, align, sideOffset, class: 'popover-content'}, contentProps) as PopoverContentProps}>
            <SnippetOrString value={popover}/>
        </PopoverPrimitive.Content>
    </PopoverPrimitive.Portal>
</PopoverPrimitive.Root>

<style>
    :global(.popover-content) {
        --popover-bg: var(--color-surface-raised);

        z-index: 50;
        width: calc(0.25rem * 72);
        border-radius: var(--corner-md);
        border: var(--border);
        background-color: var(--popover-bg);
        padding: var(--space-4, calc(0.25rem * 4));
        box-shadow: var(--elevation-1);
        max-height: calc(var(--bits-popover-content-available-height, 999px) - var(--space-4));
        overflow: auto;

        &[data-state="open"] {
            animation: popover-in 120ms var(--easing-default, ease);
        }

        &[data-state="closed"] {
            animation: popover-out 100ms var(--easing-default, ease);
        }
    }

    @keyframes popover-in {
        from {
            opacity: 0;
            scale: 0.97;
        }
        to {
            opacity: 1;
            scale: 1;
        }
    }

    @keyframes popover-out {
        from {
            opacity: 1;
            scale: 1;
        }
        to {
            opacity: 0;
            scale: 0.97;
        }
    }
</style>
