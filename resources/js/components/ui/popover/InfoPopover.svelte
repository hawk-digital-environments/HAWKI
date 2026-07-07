<!--
  @component Inline info button that reveals a hoverable Popover. Renders a
  small icon (default: Info from Lucide) that opens the `info` content on
  hover or click. Useful for contextual help next to form labels or settings.
-->
<script lang="ts">

    import type {Component, ComponentProps} from 'svelte';
    import Popover from '$lib/components/ui/popover/Popover.svelte';
    import {mergeProps} from 'bits-ui';
    import {Info, type LucideProps} from '@lucide/svelte';

    type PopoverProps = ComponentProps<typeof Popover>;

    interface Props {
        /** The content to display inside the popover. Can be a string or a Svelte snippet. */
        info: PopoverProps['popover'];
        /** Icon component to render on the trigger button. Defaults to the Lucide `Info` icon. */
        icon?: Component<LucideProps>;
        /** Preferred side for the popover relative to the trigger. Defaults to `'top'`. */
        popoverSide?: PopoverProps['side'];
        /** Alignment of the popover relative to the trigger. Defaults to `'center'`. */
        popoverAlign?: PopoverProps['align'];
        /** Additional props forwarded to the Popover content element. */
        popoverContentProps?: PopoverProps['contentProps'];
        /** Accessible name for the info trigger button. */
        ariaLabel?: string;
        /** Extra props merged onto the trigger button. */
        triggerProps?: Record<string, unknown>;
        /** Bindable reference to the rendered trigger button. */
        triggerEl?: HTMLButtonElement | null;
    }

    let {
        info,
        icon: Icon = Info,
        popoverSide = 'top',
        popoverAlign = 'center',
        popoverContentProps,
        ariaLabel,
        triggerProps,
        triggerEl = $bindable(null)
    }: Props = $props();
</script>

<Popover side={popoverSide}
         group="info-popovers"
         align={popoverAlign}
         sideOffset={4}
         openOnHover={true}
         contentProps={mergeProps(popoverContentProps, {class: 'info-button-popover'})}
         popover={info}>
    {#snippet children(a)}
        <button
            bind:this={triggerEl}
            {...mergeProps(
                a?.props ?? {},
                triggerProps ?? {},
                ariaLabel ? {'aria-label': ariaLabel} : {}
            ) as Record<string, unknown>}
            class="info-button">
            <Icon size="15"/>
        </button>
    {/snippet}
</Popover>

<style>
    .info-button {
        display: inline-block;
        line-height: 0;
        padding: 0;
        border: none;
        background: none;
        color: var(--color-text-muted);
        cursor: help;
        stroke: currentColor;

        &[data-state*="open"] {
            :global(svg.lucide) {
                stroke-width: 3;
            }
        }
    }

    :global(.info-button-popover) {
        font-size: var(--font-size-xxs);
        max-width: 300px;
        max-height: 400px;
    }
</style>
