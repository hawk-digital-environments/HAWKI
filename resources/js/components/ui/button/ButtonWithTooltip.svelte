<!--
  @component Button with a built-in Tooltip. Combines Button and Tooltip so
  callers don't need to wire the two primitives together manually. All Button
  props are forwarded; tooltip placement and delay are configured through the
  `tooltip*` props.

  Useful for icon-only/`ghost` buttons that need an accessible label on hover,
  e.g. a toolbar trigger. `highlight` (inherited from Button) can be fed the
  trigger's `data-state` so the button looks active while an attached menu is open.

  @example
  ```svelte
  <ButtonWithTooltip
      variant="ghost"
      iconLeft={PlusSignIcon}
      tooltip="Manage tools"
      tooltipSide="bottom"
  />
  ```
-->
<script lang="ts">

    import type {ComponentProps} from 'svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {mergeProps} from 'bits-ui';

    type TooltipProps = ComponentProps<typeof Tooltip>;

    interface Props extends ComponentProps<typeof Button> {
        /** Delay in ms before the tooltip appears on hover. Forwarded to `Tooltip`. */
        tooltipDelayDuration?: TooltipProps['delayDuration'];
        /** Tooltip content: plain text or a Svelte snippet. */
        tooltip: TooltipProps['tooltip'];
        /** Preferred side of the button the tooltip opens on. Forwarded to `Tooltip`. */
        tooltipSide?: TooltipProps['side'];
        /** Offset in px between the button and the tooltip. Forwarded to `Tooltip`. */
        tooltipSideOffset?: TooltipProps['sideOffset'];
    }

    let {
        ref = $bindable(null),
        tooltipDelayDuration,
        tooltip,
        tooltipSide,
        tooltipSideOffset,
        ...buttonProps
    }: Props = $props();

    const labelledButtonProps = $derived.by(() => {
        const hasAccessibleName = buttonProps['aria-label'] || buttonProps['aria-labelledby'];
        if (!buttonProps.children && !hasAccessibleName && typeof tooltip === 'string') {
            return {...buttonProps, 'aria-label': tooltip};
        }
        return buttonProps;
    });
</script>

<Tooltip
    delayDuration={tooltipDelayDuration}
    tooltip={tooltip}
    side={tooltipSide}
    sideOffset={tooltipSideOffset}
>
    {#snippet children(a)}
        <Button bind:ref={ref} {...mergeProps(labelledButtonProps, a.props)}/>
    {/snippet}
</Tooltip>
