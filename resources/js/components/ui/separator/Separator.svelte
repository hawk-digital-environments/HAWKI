<!--
  @component Visual divider between content sections.
  Horizontal by default; set orientation="vertical" for a column divider.
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Layout direction of the separator. */
        orientation?: 'horizontal' | 'vertical';
        /** When true, the separator is purely decorative and hidden from assistive tech. */
        decorative?: boolean;
    }

    const {orientation = 'horizontal', decorative = true, ...restProps}: Props = $props();

    const ariaProps = $derived(decorative
        ? {'aria-hidden': true as const}
        : {role: 'separator' as const, 'aria-orientation': orientation});
</script>

<div
    data-orientation={orientation}
    {...mergeProps({class: `separator separator--${orientation}`}, ariaProps, restProps) as HTMLAttributes<HTMLDivElement>}
></div>

<style>
    .separator {
        flex-shrink: 0;
        background-color: var(--color-border);
    }

    .separator--horizontal {
        height: 1px;
        width: 100%;
    }

    .separator--vertical {
        height: 100%;
        width: 1px;
    }
</style>
