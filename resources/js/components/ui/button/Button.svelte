<!--
  @component General-purpose button primitive.

  Supports five `variant` styles — `fill`, `stroke`, `ghost`, `iconGhost`, `delete` —
  and five `size` options — `xs`, `sm`, `md`, `lg`, `icon`. Renders a native
  `<button>` element and forwards all HTML button attributes via rest-props.

  The `md` size expects optional `.icon` and `.label` child elements for the
  two-column icon + label layout; other sizes leave layout to the caller.
-->
<script module lang="ts">
    import {cva, type VariantProps} from 'class-variance-authority';

    const buttonVariants = cva('btn', {
        variants: {
            variant: {
                fill: 'btn--fill',
                stroke: 'btn--stroke',
                ghost: 'btn--ghost',
                iconGhost: 'btn--iconGhost',
                delete: 'btn--delete'
            },
            size: {
                xs: 'btn--xs',
                sm: 'btn--sm',
                md: 'btn--md',
                // This is an internal size used when an icon is provided without children.
                // It can not be set directly via the `size` prop.
                iconOnly: 'btn--iconOnly'
            }
        },
        defaultVariants: {variant: 'fill', size: 'md'}
    });

    export type ButtonVariant = VariantProps<typeof buttonVariants>['variant'];
    export type ButtonSize = Exclude<VariantProps<typeof buttonVariants>['size'], 'iconOnly'>;
</script>

<script lang="ts">
    import type {HTMLButtonAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import type {Component} from 'svelte';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    interface Props extends HTMLButtonAttributes {
        /** The reference to the underlying button element. Can be used for imperative actions like focusing the button. */
        ref?: HTMLButtonElement | null;
        /** Visual style variant of the button. */
        variant?: ButtonVariant;
        /** Size variant of the button. */
        size?: ButtonSize;
        /** The icon to display on the left side of the button. */
        iconLeft?: IconComponent;
        /** The icon to display on the right side of the button. */
        iconRight?: IconComponent;
        /** If true, the button will take the full width of its container. */
        block?: boolean;
        /** If true or a string value of "active", "true", "1", or "open", the button will be styled as active. */
        highlight?: boolean | string;
    }

    let {
        ref = $bindable(null),
        variant,
        size,
        children,
        iconLeft: IconLeft,
        iconRight: IconRight,
        highlight,
        ...restProps
    }: Props = $props();

    const forceActive = $derived.by(() => {
        if (typeof highlight === 'boolean') {
            return highlight;
        }
        const activeValues = ['active', 'true', '1', 'open', 'delayed-open', 'instant-open'];
        return activeValues.includes(String(highlight).toLowerCase());
    });

</script>

<button bind:this={ref} {...mergeProps(
    {
        class: buttonVariants({
                variant,
                size: ((IconLeft || IconRight) && !children) ? 'iconOnly' : size
            }
        )
    },
    {
        class: {
            'btn--block': restProps.block,
            'btn--active': forceActive
        }
    },
    restProps
)}>
    {#if IconLeft}
        <IconLeft class="btnIcon"/>
    {/if}
    {@render children?.()}
    {#if IconRight}
        <IconRight class="btnIcon"/>
    {/if}
</button>

<style>
    /* ── Base ──────────────────────────────────────────────────────────── */

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: space-between;
        border: none;
        font-family: inherit;
        cursor: pointer;
        box-sizing: border-box;
        border: var(--border);
        color: var(--color-text);
        transition-property: color, background-color, border-color, opacity, text-decoration;
        transition-timing-function: var(--easing-default, cubic-bezier(0.4, 0, 0.2, 1));
        transition-duration: var(--duration-fast, 150ms);
        gap: var(--space-2);
        flex-shrink: 0;
        user-select: none;

        &:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        &:focus {
            outline: 1px solid var(--color-focus-ring, var(--color-interactive));
            outline-offset: 2px;
        }
    }

    :global(.btnIcon) {
        pointer-events: none;
    }

    /* ── Variants ──────────────────────────────────────────────────────── */

    .btn--fill {
        --btn-bg: var(--color-interactive);
        --btn-color: var(--color-on-interactive);

        background: var(--btn-bg);
        color: var(--btn-color);
        font-weight: var(--font-weight-normal);
        border-color: transparent;

        &:not(:disabled):hover {
            --btn-bg: var(--color-interactive-hover);
        }

        &:disabled {
            opacity: 1;
            --btn-bg: var(--color-bg-secondary);
            --btn-color: var(--color-text-disabled);
        }
    }

    .btn--stroke {
        --btn-bg: transparent;
        --btn-color: var(--color-text);

        background: var(--btn-bg);
        color: var(--btn-color);
        border: var(--border);

        &:not(:disabled):hover {
            --btn-bg: var(--color-hover);
            --btn-color: var(--color-text);
        }

        &:not(:disabled):active {
            --btn-bg: var(--color-hover);
            --btn-color: var(--color-text);
        }
    }

    .btn--ghost {
        --btn-bg: transparent;
        --btn-color: var(--color-text);

        background: var(--btn-bg);
        color: var(--btn-color);
        border-color: transparent;

        &:not(:disabled):hover {
            --btn-bg: var(--color-hover);
            --btn-color: var(--color-text);
        }

        &:not(:disabled):active {
            --btn-bg: var(--color-hover);
            --btn-color: var(--color-text);
        }
    }

    .btn--iconGhost {
        --btn-bg: transparent;
        --btn-color: var(--color-text-muted);

        background: var(--btn-bg);
        color: var(--btn-color);
        border-color: transparent;

        &:not(:disabled):hover,
        &:not(:disabled):active {
            --btn-bg: transparent;
            --btn-color: var(--color-text);
        }
    }

    .btn--active {
        --btn-bg: var(--color-hover);
        --btn-color: var(--color-text);
    }

    .btn--delete {
        --btn-bg: var(--color-error);
        --btn-color: var(--color-text-invert);

        background: var(--btn-bg);
        color: var(--btn-color);
        border-color: transparent;

        &:not(:disabled):hover {
            --btn-bg: color-mix(in oklch, var(--color-error) 85%, black);
        }
    }

    /* ── Sizes ─────────────────────────────────────────────────────────── */

    .btn--xs {
        --btn-icon-size: 14px;
        width: fit-content;
        height: 2rem;
        column-gap: var(--space-1);
        padding: 0 var(--space-2);
        font-size: var(--font-size-xs);
        border-radius: var(--corner-full);

        &:not(:has(> :global(.btnIcon))) {
            padding-inline: var(--space-3);
        }

        &:has(> :global(.btnIcon):first-child) {
            padding-inline-start: var(--space-3);
        }

        &:has(> :global(.btnIcon):last-child) {
            padding-inline-end: var(--space-3);
        }
    }

    .btn--sm {
        --btn-icon-size: 16px;
        width: fit-content;
        height: 2rem;
        padding: 0 var(--space-2);
        font-size: var(--font-size-sm);
        border-radius: var(--corner-full);

        &:not(:has(> :global(.btnIcon))) {
            padding-inline: var(--space-3);
        }

        &:has(> :global(.btnIcon):first-child) {
            padding-inline-start: var(--space-3);
        }

        &:has(> :global(.btnIcon):last-child) {
            padding-inline-end: var(--space-3);
        }
    }

    .btn--md {
        --btn-icon-size: 18px;
        position: relative;
        display: grid;
        grid-template-columns: auto 1fr;
        column-gap: var(--space-3);
        align-items: center;
        height: 2.5rem;
        min-width: 6rem;
        padding: var(--space-2) var(--space-4);
        overflow: hidden;
        font-size: var(--font-size-md);
        border-radius: var(--corner-full);

        &:not(:has(> :global(.btnIcon))) {
            padding-inline: calc(var(--space-4) + var(--space-1));
        }

        &:has(> :global(.btnIcon):first-child) {
            padding-inline-start: calc(var(--space-4) + var(--space-1));
        }

        &:has(> :global(.btnIcon):last-child) {
            padding-inline-end: calc(var(--space-4) + var(--space-1));
        }
    }

    /* ── Modifiers ──────────────────────────────────────────────────────── */

    .btn > :global(.btnIcon) {
        stroke: currentColor;
        width: var(--btn-icon-size, 18px);
        height: var(--btn-icon-size, 18px);
    }

    .btn--iconOnly {
        width: 2rem;
        height: 2rem;
        padding: 0;
        flex-shrink: 0;
        justify-content: center;
        border-radius: var(--corner-full);
    }

    .btn--iconGhost.btn--iconOnly {
        width: 1.5rem;
        height: 1.5rem;
    }

    .btn--block {
        width: 100%;
    }
</style>
