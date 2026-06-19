<!--
  @component Full dropdown menu with trigger and content panel.
  Wraps bits-ui DropdownMenu and hides the low-level Root/Trigger/Portal/Content primitives.
  Place DropdownMenuItem, DropdownMenuGroup, DropdownMenuLabel,
  DropdownMenuSeparator, DropdownMenuCheckboxItem and DropdownMenuRadioGroup
  inside the `children` snippet.
-->
<script lang="ts">
    import {DropdownMenu as DropdownMenuPrimitive, type DropdownMenuContentProps, mergeProps} from 'bits-ui';
    import type {Snippet} from 'svelte';
    import Breakpoint from '$lib/components/util/breakpoints/Breakpoint.svelte';
    import BottomSheet from '$lib/components/ui/sheet/BottomSheet.svelte';
    import SnippetOrString from '$lib/components/util/snippetOrString/SnippetOrString.svelte';

    interface Props {
        /** Whether the dropdown is open. Supports bind:open. */
        open?: boolean;
        /** When true, the menu cannot be opened. */
        disabled?: boolean;
        /** A title to render on top of the menu (both as dropdown and sheet). */
        title?: Snippet | string;
        /**
         * The element that opens the menu. Can be a string (rendered as a `<button>`)
         * or a Snippet that receives `{ props }` — the props MUST be spread on the
         * snippet's root element so accessibility and keyboard handling work correctly.
         */
        trigger?: Snippet<[{ props: Record<string, any> }]> | string;
        /** Menu items rendered inside the content panel. */
        children?: Snippet;
        /** Preferred side relative to the trigger. */
        side?: 'top' | 'right' | 'bottom' | 'left';
        /** Alignment relative to the trigger. Defaults to 'start' or 'end' based on screen position. */
        align?: 'start' | 'center' | 'end';
        /** Pixel offset from the trigger. */
        sideOffset?: number;
        /** Additional props forwarded to the DropdownMenu.Content element. */
        contentProps?: Omit<DropdownMenuContentProps, 'children'>;
    }

    let {
        open = $bindable(false),
        disabled,
        title,
        trigger,
        children,
        side = 'bottom',
        align = undefined,
        sideOffset = 4,
        contentProps
    }: Props = $props();

    let triggerEl = $state<HTMLElement | null>(null);
    const onOpenChange = (openNew: boolean) => {
        open = openNew;
    };

    function resolvedAlign(): 'start' | 'center' | 'end' {
        if (align) return align;
        if (triggerEl) {
            const {left, right} = triggerEl.getBoundingClientRect();
            const mid = (left + right) / 2;
            return mid < window.innerWidth / 2 ? 'start' : 'end';
        }
        return 'center';
    }

    const fullContentProps = $derived.by(() => {
        return mergeProps(
            {
                side,
                align: resolvedAlign(),
                sideOffset,
                class: 'dropdown-content'
            },
            contentProps ?? {}
        ) as DropdownMenuContentProps;
    });
</script>

<DropdownMenuPrimitive.Root bind:open {onOpenChange}>
    {#if trigger}
        <DropdownMenuPrimitive.Trigger disabled={disabled}>
            {#snippet child({props})}
                {#if typeof trigger === 'string'}
                    <button bind:this={triggerEl} {...props} type="button">{trigger}</button>
                {:else}
                    <span bind:this={triggerEl} style="display:contents">
                        {@render trigger({props})}
                    </span>
                {/if}
            {/snippet}
        </DropdownMenuPrimitive.Trigger>
    {/if}
    <Breakpoint>
        {#snippet bpSmallerThanMd()}
            <BottomSheet bind:open={open} title={title}>
                <DropdownMenuPrimitive.ContentStatic {...mergeProps(fullContentProps, {class: 'dropdown-content--sheet'}) as any}>
                    {@render children?.()}
                </DropdownMenuPrimitive.ContentStatic>
            </BottomSheet>
        {/snippet}
        {#snippet children()}
            <DropdownMenuPrimitive.Portal>
                <DropdownMenuPrimitive.Content {...mergeProps(fullContentProps, {class: 'dropdown-content--dropdown'}) as any}>
                    {#if title}
                        <div class="dropdown-title">
                            <SnippetOrString value={title}/>
                        </div>
                    {/if}
                    {@render children?.()}
                </DropdownMenuPrimitive.Content>
            </DropdownMenuPrimitive.Portal>
        {/snippet}
    </Breakpoint>
</DropdownMenuPrimitive.Root>

<style>
    :global(.dropdown-content.dropdown-content--dropdown) {
        --dropdown-bg: var(--color-surface-raised);

        min-width: 8rem;
        border-radius: var(--corner-md);
        border: var(--border);
        background-color: var(--dropdown-bg);
        padding: var(--space-1, 0.25rem);
        box-shadow: var(--elevation-1);
        max-height: calc(var(--bits-dropdown-menu-content-available-height, 999px) - var(--space-4));
        overflow: auto;
    }

    :global(.dropdown-content[data-state="open"]) {
        animation: dropdown-in var(--duration-fast, 150ms) var(--easing-default, ease);
    }

    :global(.dropdown-content[data-state="closed"]) {
        animation: dropdown-out var(--duration-fast, 100ms) var(--easing-default, ease);
    }

    .dropdown-title {
        padding-inline: var(--space-2, calc(0.25rem * 2));
        padding-block: var(--space-1_5);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium, 500);
        color: var(--color-text);
    }

    @keyframes dropdown-in {
        from {
            opacity: 0;
            scale: 0.97;
        }
        to {
            opacity: 1;
            scale: 1;
        }
    }

    @keyframes dropdown-out {
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
