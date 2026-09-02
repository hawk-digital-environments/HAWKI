<!--
  @component General-purpose button primitive.

  Supports seven `variant` styles — `fill`, `accent`, `surface`, `stroke`, `ghost`,
  `iconGhost`, `delete` — and three `size` options — `xs`, `sm`, `md`. Renders a native
  `<button>` element and forwards all `HTMLButtonAttributes` via rest-props.

  If `iconLeft` and/or `iconRight` are given without `children`, the button
  automatically switches to a compact icon-only square (internal `iconOnly`
  size) — no separate prop needed for that case.

  Three modifiers cover the "button that opens a menu" case, so menu triggers don't have to
  restyle the button from outside: `disclosure` turns the icon into a disclosure chevron that
  flips while the attached menu reports `data-state="open"`, `truncate` lets a long label
  ellipsize instead of pushing its neighbours around, and `iconSize` overrides the size the
  button's own size class gives its icons.

  @example
  ```svelte
  <Button
      iconRight={Cancel01Icon}
      disabled={sending}
      onclick={() => mode.exit()}
      title="Cancel"
      variant="ghost"
      size="xs"
  />
  ```

  @example A labelled menu trigger:
  ```svelte
  <Button
      variant="surface"
      size="xs"
      iconLeft={ChevronDownIcon}
      iconSize={18}
      disclosure
      truncate
      {...triggerProps}
  >
      {model.label}
  </Button>
  ```
-->
<script module lang="ts">
    import {cva, type VariantProps} from 'class-variance-authority';

    const buttonVariants = cva('btn', {
        variants: {
            variant: {
                fill: 'btn--fill',
                accent: 'btn--accent',
                surface: 'btn--surface',
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
        /** Marks the button as opening something: its icon sits closer to the label and flips
         *  while the button carries `data-state="open"` (as menu/select triggers do). */
        disclosure?: boolean;
        /** Lets the label ellipsize and the button shrink below its content width, for a
         *  trigger whose text is data (a model name, a file name) rather than a fixed word. */
        truncate?: boolean;
        /** Overrides the icon size the button's `size` would otherwise set, in px or any
         *  CSS length. */
        iconSize?: number | string;
    }

    let {
        ref = $bindable(null),
        variant,
        size,
        children,
        iconLeft: IconLeft,
        iconRight: IconRight,
        highlight,
        disclosure = false,
        truncate = false,
        iconSize,
        ...restProps
    }: Props = $props();

    const iconSizeStyle = $derived(
        iconSize === undefined
            ? undefined
            : `--btn-icon-size: ${typeof iconSize === 'number' ? `${iconSize}px` : iconSize};`
    );

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
            'btn--active': forceActive,
            'btn--disclosure': disclosure,
            'btn--truncate': truncate
        },
        style: iconSizeStyle
    },
    restProps
)}>
    {#if IconLeft}
        <IconLeft class="btnIcon" aria-hidden="true"/>
    {/if}
    {#if truncate}
        <span class="btn-label">{@render children?.()}</span>
    {:else}
        {@render children?.()}
    {/if}
    {#if IconRight}
        <IconRight class="btnIcon" aria-hidden="true"/>
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

        &:focus-visible {
            outline: 2px solid var(--color-focus-ring, var(--color-interactive));
            outline-offset: 2px;
        }
    }

    :global(.btnIcon) {
        pointer-events: none;
    }

    /* ── Variants ──────────────────────────────────────────────────────── */

    .btn--accent:not(:disabled) {
        --btn-bg: var(--color-accent-fill);
        --btn-color: var(--color-on-interactive);
        --btn-bg-hover: var(--color-accent-fill-hover);
    }

    :global(html.darkMode) .btn--accent:not(:disabled) {
        --btn-color: var(--color-active-text);
    }

    .btn--fill,
    .btn--accent {
        --btn-bg: var(--color-interactive);
        --btn-bg-hover: var(--color-interactive-hover);
        --btn-color: var(--color-on-interactive);

        background: var(--btn-bg);
        color: var(--btn-color);
        font-weight: var(--font-weight-normal);
        border-color: transparent;

        &:not(:disabled):hover {
            --btn-bg: var(--btn-bg-hover);
        }

        &:disabled {
            opacity: 1;
            --btn-bg: var(--color-disabled-bg);
            --btn-color: var(--color-text-disabled);
        }
    }

    /* A neutral filled pill that sits a step lighter than the surface it is on, so the
       darker hover below reads as a change. The resting state of a menu trigger that should
       look like a control rather than a bare label. */
    .btn--surface {
        --btn-bg: var(--color-surface-light);
        --btn-color: var(--color-text);

        background: var(--btn-bg);
        color: var(--btn-color);
        border-color: transparent;

        &:not(:disabled):hover,
        &:not(:disabled):active,
        &[data-state='open'] {
            --btn-bg: var(--color-hover);
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

    /* The chevron of a disclosure sits closer to its label than an action icon does — it
       belongs to the label rather than standing beside it. */
    .btn--disclosure {
        column-gap: var(--space-0_5);
    }

    .btn--disclosure > :global(.btnIcon) {
        color: var(--color-text-muted);
        transition: transform var(--duration-fast, 150ms) var(--easing-default, ease);
    }

    .btn--disclosure[data-state='open'] > :global(.btnIcon) {
        transform: rotate(-180deg);
    }

    /* Shrinkable, so a long label truncates instead of pushing whatever shares its row. */
    .btn--truncate {
        flex-shrink: 1;
        min-width: 0;
    }

    .btn--truncate > .btn-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
