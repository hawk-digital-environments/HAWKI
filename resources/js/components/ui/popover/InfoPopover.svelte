<!--
  @component Inline info button that reveals a hoverable Popover. Renders a
  small icon-only button that opens the `info` content on hover or click.
  Useful for contextual help next to form labels or settings — a thin,
  pre-configured wrapper around `Popover` (icon trigger, `group="info-popovers"`
  so only one info popover is open at a time, `openOnHover`).

  The icon-only trigger needs an accessible name: pass either `label` (the
  name of the thing being explained — the button is then named "Info: {label}")
  or an explicit `ariaLabel`. While open, the popover content is linked to the
  trigger via `aria-describedby`.

  Usage — plain text info, placed right after a label:
    <Txt size="xs">
        {__('chat.composer.settings.temperature')}
        <InfoPopover
            label={__('chat.composer.settings.temperature')}
            info={__('chat.composer.settings.temperatureInfo')}/>
    </Txt>

  Usage — rich content via a snippet, custom icon and side:
    <InfoPopover popoverSide="right" icon={Settings01Icon} ariaLabel="About Top P">
        {#snippet info()}
            <strong>Top P</strong> controls nucleus sampling.
        {/snippet}
    </InfoPopover>
-->
<script lang="ts">

    import type {ComponentProps} from 'svelte';
    import Popover from '$lib/components/ui/popover/Popover.svelte';
    import {mergeProps} from 'bits-ui';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import InformationCircleIcon from '$lib/components/ui/icons/iconset/InformationCircleIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    type PopoverProps = ComponentProps<typeof Popover>;

    /** Either an explicit accessible name or the label of the described item is required. */
    type Naming =
        | { ariaLabel: string; label?: string }
        | { label: string; ariaLabel?: string };

    type Props = Naming & {
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
        /** Accessible name for the info trigger button. Defaults to "Info: {label}". */
        ariaLabel?: string;
        /** Label of the item this info describes; used to derive the accessible name. */
        label?: string;
        /** Extra props merged onto the trigger button. */
        triggerProps?: Record<string, unknown>;
        /** Bindable reference to the rendered trigger button. */
        triggerEl?: HTMLButtonElement | null;
        /** Disable the Popover */
        disabled?: boolean;
    };

    let {
        info,
        icon: Icon = InformationCircleIcon,
        popoverSide = 'top',
        popoverAlign = 'center',
        popoverContentProps,
        ariaLabel,
        label,
        triggerProps,
        triggerEl = $bindable(null),
        disabled = false,
    }: Props = $props();

    const uid = $props.id();
    const contentId = `${uid}-info`;
    let open = $state(false);
    const accessibleName = $derived(ariaLabel ?? __('ui.infoPopover.triggerLabel', {label: label ?? ''}));
</script>

{#if disabled}
    {@render popoverButton({ props: { "data-disabled": "" } })}
{:else}
    <Popover side={popoverSide}
             bind:open
             group="info-popovers"
             align={popoverAlign}
             sideOffset={4}
             openOnHover={true}
             contentProps={mergeProps(popoverContentProps, {class: 'info-button-popover', id: contentId})}
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
            {
                'aria-label': accessibleName,
                'aria-describedby': open && !disabled ? contentId : undefined
            }
        ) as Record<string, unknown>}
        type="button"
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
