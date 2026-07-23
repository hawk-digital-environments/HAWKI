<script module lang="ts">
    import {cva, type VariantProps} from 'class-variance-authority';

    const badgeVariants = cva('badge', {
        variants: {
            variant: {
                default: 'badge--default',
                secondary: 'badge--secondary',
                destructive: 'badge--destructive',
                outline: 'badge--outline',
            },
        },
        defaultVariants: {variant: 'default'},
    });

    export type BadgeVariant = VariantProps<typeof badgeVariants>['variant'];
    export {badgeVariants};
</script>
<!--
  @component Small status/label chip. Supports default, secondary, destructive, and outline variants.
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Visual style variant. */
        variant?: BadgeVariant;
    }

    const {variant, children, ...restProps}: Props = $props();
</script>

<div {...mergeProps({class: badgeVariants({variant})}, restProps)}>
    {@render children?.()}
</div>

<style>
    .badge {
        display: inline-flex;
        align-items: center;
        border-radius: var(--corner-full);
        border: 1px solid transparent;
        padding-inline: var(--space-2);
        padding-block: var(--space-0_5);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-semibold, 600);
        line-height: var(--line-height-tight);
        white-space: nowrap;
    }

    /* ── Variants ─────────────────────────────────────────────────────── */

    .badge--default {
        background-color: var(--color-surface);
        color: var(--color-text);
        border-color: transparent;
    }

    .badge--secondary {
        background-color: var(--color-bg-secondary);
        color: var(--color-text-muted);
        border-color: transparent;
    }

    .badge--destructive {
        background-color: var(--color-error);
        color: var(--color-text-invert);
        border-color: transparent;
    }

    .badge--outline {
        background-color: transparent;
        color: var(--color-text);
        border-color: var(--color-border);
    }
</style>
