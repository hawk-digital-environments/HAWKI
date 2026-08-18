<!--
  @component Inline info button that reveals a hoverable Popover. Renders a
  small icon-only button that opens the `info` content on hover or click.
  Useful for contextual help next to form labels or settings — a thin,
  pre-configured wrapper around `Popover` (icon trigger, `group="info-popovers"`
  so only one info popover is open at a time, `openOnHover`).

  Usage — plain text info, placed right after a label:
    <Txt size="xs">
        {__('chat.composer.settings.temperature')}
        <InfoPopover info={__('chat.composer.settings.temperatureInfo')}/>
    </Txt>

  Usage — rich content via a snippet, custom icon and side:
    <InfoPopover popoverSide="right" icon={Settings01Icon}>
        {#snippet info()}
            <strong>Top P</strong> controls nucleus sampling.
        {/snippet}
    </InfoPopover>
-->
<script lang="ts">

    import type {ComponentProps} from 'svelte';
    import Popover from './Popover.svelte';
    import {mergeProps} from 'bits-ui';
    import type {IconComponent} from '../icons/index.js';
    import InformationCircleIcon from '../icons/iconset/InformationCircleIcon.svelte';

    type PopoverProps = ComponentProps<typeof Popover>;

    interface Props {
        /** The content to display inside the popover. Can be a string or a Svelte snippet. */
        info: PopoverProps['popover'];
        /** Icon component to render on the trigger button. Defaults to the Lucide `Info` icon. */
        icon?: IconComponent;
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
        /** Disable the Popover */
        disabled?: boolean;
    }

    let {
        info,
        icon: Icon = InformationCircleIcon,
        popoverSide = 'top',
        popoverAlign = 'center',
        popoverContentProps,
        ariaLabel,
        triggerProps,
        triggerEl = $bindable(null),
        disabled = false,
    }: Props = $props();
</script>

{#if disabled}
    {@render popoverButton({ props: { "data-disabled": "" } })}
{:else}
    <Popover side={popoverSide}
             group="info-popovers"
             align={popoverAlign}
             sideOffset={4}
             openOnHover={true}
             contentProps={mergeProps(popoverContentProps, {class: 'info-button-popover'})}
             popover={info}>
        {#snippet children(a)}
            {@render popoverButton(a)}
        {/snippet}
    </Popover>
{/if}

{#snippet popoverButton(a: Record<string, any>)}
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
            :global(svg) {
                stroke-width: 3;
            }
        }

        &[data-disabled] {
            cursor: not-allowed;
            color: var(--color-text-disabled)
        }
    }

    :global(.info-button-popover) {
        font-size: var(--font-size-xxs);
        max-width: 300px;
        max-height: 400px;
    }
</style>
