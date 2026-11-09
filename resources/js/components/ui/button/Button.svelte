<!--
  @component General-purpose button primitive.

  Supports six `variant` styles — `fill`, `surface`, `stroke`, `ghost`, `iconGhost`,
  `delete` (destructive) — and three `size` options — `xs`, `sm`, `md`. Renders a native
  `<button>` element and forwards all `HTMLButtonAttributes` via rest-props.

  Every variant shares one state model: hover, press, `highlight` and an attached menu's
  `data-state="open"` all show the variant's hover look, and a disabled button always shows
  the disabled text color (plus the disabled fill, for variants that have a fill).

  If `iconLeft` and/or `iconRight` are given without `children`, the button
  automatically switches to an icon-only square as tall as its `size` (`sm` when no
  size is given) — no separate prop needed for that case.

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
                surface: 'btn--surface',
                stroke: 'btn--stroke',
                ghost: 'btn--ghost',
                iconGhost: 'btn--iconGhost',
                delete: 'btn--delete'
            },
            size: {
                xs: 'btn--xs',
                sm: 'btn--sm',
                md: 'btn--md'
            }
        },
        defaultVariants: {variant: 'fill', size: 'md'}
    });

    export type ButtonVariant = VariantProps<typeof buttonVariants>['variant'];
    export type ButtonSize = VariantProps<typeof buttonVariants>['size'];
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
        block = false,
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

    const iconOnly = $derived(Boolean((IconLeft || IconRight) && !children));

</script>

<button bind:this={ref} {...mergeProps(
    {
        class: buttonVariants({
                variant,
                size: iconOnly ? (size ?? 'sm') : size
            }
        )
    },
    {
        class: {
            'btn--iconOnly': iconOnly,
            'btn--block': block,
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

    /*
      A variant only sets the input properties below; the base rule and the two state rules
      turn them into paint. So every variant gets the same states (hover, press, highlight,
      open menu, disabled), and a theme override or a caller only ever swaps an input instead
      of out-specifying a state rule. `ghost` is these defaults as they stand.
    */
    .btn {
        --btn-bg: transparent;
        --btn-color: var(--color-text);
        --btn-border-color: transparent;
        --btn-bg-hover: var(--color-hover);
        --btn-color-hover: var(--btn-color);
        --btn-bg-disabled: transparent;
        --btn-color-disabled: var(--color-text-disabled);

        display: inline-flex;
        align-items: center;
        justify-content: space-between;
        font-family: inherit;
        font-weight: var(--font-weight-normal);
        cursor: pointer;
        box-sizing: border-box;
        border: var(--divider-width) solid var(--btn-border-color);
        background: var(--btn-bg);
        color: var(--btn-color);
        transition-property: color, background-color, border-color, opacity, text-decoration;
        transition-timing-function: var(--easing-default, cubic-bezier(0.4, 0, 0.2, 1));
        transition-duration: var(--duration-fast, 150ms);
        gap: var(--space-2);
        flex-shrink: 0;
        user-select: none;

        /* The border clears so the fill, which paints under it, shows through: a filled
           outline reads as one pill instead of a fill inside a darker ring. Painting the
           border in the fill colour instead would double a translucent fill (`delete`)
           into a ring of its own. */
        &:not(:disabled):is(:hover, :active, .btn--active, [data-state='open']) {
            background: var(--btn-bg-hover);
            border-color: transparent;
            color: var(--btn-color-hover);
        }

        &:disabled {
            cursor: not-allowed;
            background: var(--btn-bg-disabled);
            color: var(--btn-color-disabled);
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

    /* The light accent tint of the sidebar's action row ("New chat"); the hover leans
       toward the text color, so it deepens the tint. */
    .btn--fill {
        --btn-bg: var(--color-accent-100);
        --btn-bg-hover: color-mix(in oklab, var(--color-accent-100) 85%, var(--color-accent-text));
        --btn-color: var(--color-accent-text);
        --btn-bg-disabled: var(--color-disabled-bg);
    }

    :global(html.darkMode) .btn--fill {
        --btn-bg-hover: var(--color-accent-fill-hover);
    }

    /* A neutral filled pill that sits a step lighter than the surface it is on, so the
       darker hover reads as a change. The resting state of a menu trigger that should
       look like a control rather than a bare label. */
    .btn--surface {
        --btn-bg: var(--color-surface-light);
        --btn-bg-disabled: var(--color-disabled-bg);
    }

    /* The ghost button with a hairline around it. */
    .btn--stroke {
        --btn-border-color: var(--color-border);
    }

    /* A bare icon: no fill in any state, only the muted glyph lifting to the text color. */
    .btn--iconGhost {
        --btn-color: var(--color-text-muted);
        --btn-bg-hover: transparent;
        --btn-color-hover: var(--color-text);
    }

    /* The destructive counterpart of the accent tint: the same light fill and deepening
       hover, in the error hue. Mixed against transparent so it tints any surface. */
    .btn--delete {
        --btn-bg: color-mix(in oklab, var(--color-error) 12%, transparent);
        --btn-bg-hover: color-mix(in oklab, var(--color-error) 22%, transparent);
        --btn-color: var(--color-error);
        --btn-bg-disabled: var(--color-disabled-bg);
    }

    /* The error red is too dark to read on dark chrome, so the label lifts toward white. */
    :global(html.darkMode) .btn--delete {
        --btn-bg: color-mix(in oklab, var(--color-error) 25%, transparent);
        --btn-bg-hover: color-mix(in oklab, var(--color-error) 35%, transparent);
        --btn-color: color-mix(in oklab, var(--color-error) 60%, white);
    }

    /* ── Sizes ─────────────────────────────────────────────────────────── */

    /* Each size pads both ends the same, whether an end holds an icon or the label. */

    .btn--xs {
        --btn-height: 2rem;
        --btn-pad: calc(var(--space-2_5) + var(--space-1));
        --btn-icon-size: 14px;
        column-gap: var(--space-1);
        font-size: var(--font-size-xs);
    }

    .btn--sm {
        --btn-height: 2rem;
        --btn-pad: calc(var(--space-2_5) + var(--space-1));
        --btn-icon-size: 16px;
        font-size: var(--font-size-sm);
    }

    .btn--md {
        --btn-height: 2.5rem;
        --btn-pad: calc(var(--space-4) + var(--space-1));
        --btn-icon-size: 18px;
        column-gap: var(--space-3);
        min-width: 6rem;
        justify-content: center;
        font-size: var(--font-size-md);
    }

    :is(.btn--xs, .btn--sm, .btn--md) {
        width: fit-content;
        height: var(--btn-height);
        padding: 0 var(--btn-pad);
        border-radius: var(--corner-full);
    }

    /* ── Modifiers ──────────────────────────────────────────────────────── */

    .btn > :global(.btnIcon) {
        stroke: currentColor;
        width: var(--btn-icon-size, 18px);
        height: var(--btn-icon-size, 18px);
    }

    /* A square as tall as its size's labelled button, so the two line up in a row. The
       glyph keeps the full 18px whatever the size; `iconSize` still overrides it. */
    .btn--iconOnly {
        --btn-icon-size: 18px;
        width: var(--btn-height);
        min-width: 0;
        padding: 0;
        flex-shrink: 0;
        justify-content: center;
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
