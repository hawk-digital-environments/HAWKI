<script module lang="ts">
    export interface SelectItemDefinition {
        /** The value of the select item. This is the value that will be set on the select when the item is selected. */
        value: string;
        /** The label of the select item. This is the text that will be displayed in the select content. */
        label: string;
        /** Whether the item is disabled. Disabled items cannot be selected and have a different style. */
        disabled?: boolean;
        /** Optional group label. If provided, items with the same group label will be grouped together in the select content. */
        groupLabel?: string;
    }

    export interface ItemSnippetProps {
        /** The item being rendered. */
        item: SelectItemDefinition,
        /** Whether this item is currently selected. */
        selected: boolean
    }
</script>
<script lang="ts">
    import {mergeProps, Select as SelectPrimitive, type WithoutChildren} from 'bits-ui';
    import type {ComponentProps, Snippet} from 'svelte';
    import SnippetOrString from '$lib/components/util/snippetOrString/SnippetOrString.svelte';
    import {ChevronDown} from '@lucide/svelte';
    import SnippetOrStringTrigger from '$lib/components/util/snippetOrString/SnippetOrStringTrigger.svelte';
    import BottomSheet from '$lib/components/ui/sheet/BottomSheet.svelte';
    import Breakpoint from '$lib/components/util/breakpoints/Breakpoint.svelte';


    type Props = Omit<WithoutChildren<SelectPrimitive.RootProps>, 'type' | 'items'> & Partial<{
        /** The list of items to display in the select. Each item should have a unique value. */
        items: SelectItemDefinition[];
        /** A snippet used to render each item. The snippet will receive the item and whether it is selected as props. If not provided a default item will be rendered */
        itemSnippet: Snippet<[ItemSnippetProps]>;
        /** Props to pass to the select trigger. */
        triggerProps?: ComponentProps<typeof SelectPrimitive.Trigger>;
        /** Content to display inside the select trigger. If not provided, the currently selected value will be displayed. */
        trigger?: string | Snippet<[{ props: Record<string, any>, Value: typeof SelectPrimitive.Value }]>;
        /** Similar to trigger, but keeps the default styling of the trigger. The snippet can be used to completely customize the label inside the trigger */
        triggerValue?: Snippet<[{ selection: Record<string, any> }]>;
        /** Props to pass to the select content. */
        contentProps?: ComponentProps<typeof SelectPrimitive.Content>;
        /** Text shown when nothing is selected. */
        placeholder?: string;
    }>

    let {
        value = $bindable(),
        items,
        itemSnippet: itemSnippet,
        triggerProps = {},
        trigger,
        triggerValue,
        contentProps = {},
        placeholder,
        ...restProps
    }: Props = $props();

    let triggerElement = $state<HTMLButtonElement>();
    let contentAlign: 'start' | 'end' = $state('start');
    let open = $state(false);

    function updateContentAlign() {
        if (!triggerElement) {
            return;
        }

        const triggerRect = triggerElement.getBoundingClientRect();
        const triggerCenter = triggerRect.left + triggerRect.width / 2;
        contentAlign = triggerCenter < window.innerWidth / 2 ? 'start' : 'end';
    }

    const hasGroups = $derived.by(() => items?.some(item => item.groupLabel));
    const groupedItems = $derived.by(() => {
        if (!items || !hasGroups) {
            return [];
        }
        const groups = new Map<string | null, SelectItemDefinition[]>();
        for (const item of items) {
            const groupLabel = item.groupLabel || '?';
            if (!groups.has(groupLabel)) {
                groups.set(groupLabel, []);
            }
            groups.get(groupLabel)!.push(item);
        }

        return Array.from(groups.keys())
            .sort((a, b) => a!.localeCompare(b!))
            .map(groupLabel => ({
                groupLabel,
                items: groups.get(groupLabel)!.sort((a, b) => a.label.localeCompare(b.label))
            }));
    });

    const fullContentProps = $derived.by(() => mergeProps(
        {
            side: 'bottom',
            align: contentProps.align ?? contentAlign,
            sideOffset: 4,
            class: 'select-content'
        },
        contentProps
    ));

</script>

{#snippet itemWrap(item: SelectItemDefinition)}
    <SelectPrimitive.Item
        value={item.value}
        disabled={item.disabled}
        label={item.label}
        class={{'select-item': true, 'select-item-grouped': hasGroups}}
    >
        {#snippet children({selected})}
            {#if itemSnippet}
                {@render itemSnippet({item, selected})}
            {:else}
                {item.label}
                {#if selected}
                    (x)
                {/if}
            {/if}
        {/snippet}
    </SelectPrimitive.Item>
{/snippet}

{#snippet viewport()}
    <SelectPrimitive.Viewport class="select-viewport">
        {#if hasGroups}
            {#each groupedItems as {groupLabel, items} (groupLabel)}
                <div class="select-group" data-group={groupLabel}>
                    <div class="select-group-label">
                        <SnippetOrString value={groupLabel ?? ''}/>
                    </div>
                    {#each items as item (item.value)}
                        {@render itemWrap(item)}
                    {/each}
                </div>
            {/each}
        {:else}
            {#each items as item (item.value)}
                {@render itemWrap(item)}
            {/each}
        {/if}
    </SelectPrimitive.Viewport>
{/snippet}

<SelectPrimitive.Root
    bind:open
    bind:value={value as never}
    items={items}
    {...mergeProps({type: 'single'}, restProps) as any}
>
    <SelectPrimitive.Trigger {...triggerProps}>
        {#snippet child({props})}
            {#if trigger}
                <SnippetOrStringTrigger value={trigger as string|Snippet} snippetArgs={{props, Value: SelectPrimitive.Value}}/>
            {:else}
                <button
                    type="button"
                    bind:this={triggerElement}
                    {...mergeProps({
                        class: 'select-trigger',
                        onfocus: updateContentAlign,
                        onpointerdown: updateContentAlign
                    }, props)}
                >
                    <ChevronDown size={18} class="select-trigger-chevron"/>
                    {#if !value && placeholder}
                        {placeholder}
                    {:else}
                        {#if triggerValue}
                            <SelectPrimitive.Value>
                                {#snippet child(props)}
                                    {@render triggerValue(props)}
                                {/snippet}
                            </SelectPrimitive.Value>
                        {:else}
                            <SelectPrimitive.Value/>
                        {/if}
                    {/if}
                </button>
            {/if}
        {/snippet}
    </SelectPrimitive.Trigger>
    <Breakpoint>
        {#snippet bpSmallerThanMd()}
            <SelectPrimitive.Portal disabled={true}>
                <BottomSheet
                    bind:open={open}
                    title={placeholder}
                    contentProps={{
                        trapFocus: false,
                        onOpenAutoFocus: (event) => {
                            // Don't focus the content when opening the sheet, keep the focus on the trigger element.
                            // We need this in order to keep the keyboard navigation working.
                            event.preventDefault();
                            triggerElement?.focus();
                        }
                    }}
                >
                    <SelectPrimitive.ContentStatic
                        {...mergeProps(fullContentProps, {class: 'select-content--sheet'}) as any}>
                        {@render viewport()}
                    </SelectPrimitive.ContentStatic>
                </BottomSheet>
            </SelectPrimitive.Portal>
        {/snippet}
        {#snippet children()}
            <SelectPrimitive.Portal>
                <SelectPrimitive.Content {...mergeProps(fullContentProps, {class: 'select-content--dropdown'})}>
                    {@render viewport()}
                </SelectPrimitive.Content>
            </SelectPrimitive.Portal>
        {/snippet}
    </Breakpoint>
</SelectPrimitive.Root>

<style>
    .select-trigger {
        display: flex;
        font-size: var(--font-size-xs);
        background: var(--color-bg-secondary);
        border: 1px solid transparent;
        border-radius: var(--corner-full);
        padding: var(--space-1) var(--space-4) var(--space-1) var(--space-2);
        align-items: center;
        gap: var(--space-1);
        color: var(--color-text);
        white-space: nowrap;
        justify-content: space-between;
        transition: background-color var(--duration-fast, 150ms) var(--easing-default);

        &:not([disabled]) {
            cursor: pointer;
        }

        &[disabled] {
            cursor: not-allowed;
        }

        &:hover {
            background-color: color-mix(in oklch, var(--color-bg-secondary) 95%, var(--color-surface-invert));
        }

        &[data-placeholder] {
            color: var(--color-text-muted);
        }

        &[data-state="open"] :global(.select-trigger-chevron) {
            transform: rotate(-180deg);
        }
    }

    .select-trigger:hover,
    .select-trigger[data-state="open"] {
        background: var(--color-hover);
    }

    :global(.select-trigger-chevron) {
        transition: transform var(--duration-fast, 150ms) var(--easing-default);
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    :global(.select-content.select-content--dropdown) {
        --select-bg: var(--color-surface-raised);

        z-index: 50;
        position: relative;
        max-height: calc(var(--bits-floating-available-height, 999px) - var(--space-4));
        overflow: hidden;
        border-radius: var(--corner-md);
        border: var(--border);
        background-color: var(--select-bg);
        box-shadow: var(--elevation-2);
        padding: var(--space-1, 0.25rem);

        &[data-state="open"] {
            animation: select-in 120ms var(--easing-default, ease);
        }

        &[data-state="closed"] {
            animation: select-out 100ms var(--easing-default, ease);
        }
    }

    :global(.select-viewport) {
        padding: 0;
    }

    :global(.select-group-label) {
        padding-inline: var(--space-2);
        padding-top: var(--space-2);
        padding-bottom: var(--space-1);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium, 500);
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--color-text-muted);
    }

    :global(.select-item) {
        display: flex;
        align-items: center;
        border-radius: var(--corner-sm);
        padding: var(--space-1);
        font-size: var(--font-size-xs);
        outline: none;
        cursor: pointer;
        transition: background-color var(--duration-fast, 150ms) var(--easing-default);

        &:global(.select-item-grouped) {
            padding-left: var(--space-4);
        }

        &[data-disabled] {
            color: var(--color-text-disabled);
            cursor: not-allowed;
        }

        &[data-highlighted] {
            background-color: var(--color-surface);
            font-weight: var(--font-weight-medium, 500);
        }

        &[data-selected] {
            background-color: var(--color-highlight);
            font-weight: var(--font-weight-medium, 500);
        }
    }

    @keyframes select-in {
        from {
            opacity: 0;
            scale: 0.97;
        }
        to {
            opacity: 1;
            scale: 1;
        }
    }

    @keyframes select-out {
        from {
            opacity: 1;
            scale: 1;
        }
        to {
            opacity: 0;
            scale: 0.97;
        }
    }

    /* ── Mobile bottom-sheet list ─────────────────────────────────────── */

    :global(.select-content.select-content--sheet) {
        :global(.select-group-label) {
            padding: var(--space-2) var(--space-2) var(--space-1);
            font-size: var(--font-size-xxs);
        }

        :global(.select-group + .select-group) {
            margin-top: var(--space-3);
        }

        :global(.select-item) {
            gap: var(--space-2);
            width: 100%;
            min-height: 2.75rem;
            padding: var(--space-2);
            font-size: var(--font-size-sm);
        }
    }
</style>
