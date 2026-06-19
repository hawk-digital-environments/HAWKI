<script lang="ts">

    import type {ComponentProps} from 'svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {mergeProps} from 'bits-ui';

    type TooltipProps = ComponentProps<typeof Tooltip>;

    interface Props extends ComponentProps<typeof Button> {
        tooltipDelayDuration?: TooltipProps['delayDuration'];
        tooltip: TooltipProps['tooltip'];
        tooltipSide?: TooltipProps['side'];
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
</script>

<Tooltip
    delayDuration={tooltipDelayDuration}
    tooltip={tooltip}
    side={tooltipSide}
    sideOffset={tooltipSideOffset}
>
    {#snippet children(a)}
        <Button bind:ref={ref} {...mergeProps(buttonProps, a.props)}/>
    {/snippet}
</Tooltip>
