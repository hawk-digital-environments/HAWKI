<!--
  @component The tinted glyph square the composer's picker rows seat their mark on —
  the tool icon in `ToolIcon`, the provider logo in `ProviderSwatch`, the MCP server
  icon in `ToolMenuGroupHeader`. One implementation of the box all of them copied:
  a fixed square tinted low-alpha with the given ink, the glyph inheriting that ink,
  and the selected state deepening the tint under a full-cover overlay (a tick by
  default; pass `state` for the mixed dash a group toggle shows).

  The ink is a CSS color value — usually a `var(--color-tool-category-*)` token or
  the muted text grey. Gray inks read heavier than colored ones, so `neutral` tints
  a step lighter; those strengths are the ones the provider swatch and the MCP rows
  already agreed on. `AssistantRow`'s emoji swatch is the one deliberate hold-out:
  its checked strength and theme-shifted check color are its own.

  @example
  <MenuSwatch ink="var(--color-tool-category-mcp)" neutral checked>
    <McpServerIcon size={16}/>
  </MenuSwatch>
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';

    interface Props extends HTMLAttributes<HTMLElement> {
        /** The glyph seated on the swatch; it inherits the ink. */
        children: Snippet;
        /** The ink for the tint and the glyph, as a CSS color value. */
        ink?: string;
        /** Gray inks read heavier than colored ones — tint a step lighter. */
        neutral?: boolean;
        /** Deepens the tint and covers the glyph with the `state` overlay. */
        checked?: boolean;
        /** What the covering overlay shows; a tick by default. Pass the mixed-state dash for group toggles. */
        state?: Snippet;
        /** `md` (default) is the row swatch; `sm` is a compact-control tile. */
        size?: 'sm' | 'md';
        /** `tile` (default) is the rounded square; `round` sits concentric in a pill; `inset` shrinks its radius for rows nested in an inset list. */
        shape?: 'tile' | 'round' | 'inset';
    }

    const {
        children,
        ink = 'var(--color-text-muted)',
        neutral = false,
        checked = false,
        state,
        size = 'md',
        shape = 'tile',
        class: className,
        ...rest
    }: Props = $props();

    const tickSize = $derived(size === 'sm' ? 14 : 16);
</script>

<!-- The name sits right next to it, so the glyph is decoration rather than content. -->
<span
    class={['menu-swatch', `menu-swatch--${size}`, `menu-swatch--${shape}`, neutral && 'menu-swatch--neutral', checked && 'menu-swatch--checked', className]}
    style:--swatch-ink={ink}
    aria-hidden="true"
    {...rest}>
    {@render children()}
    {#if checked}
        <span class="menu-swatch-state">
            {#if state}
                {@render state()}
            {:else}
                <Tick02Icon size={tickSize}/>
            {/if}
        </span>
    {/if}
</span>

<style>
    .menu-swatch {
        position: relative;
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        /* A fixed square keeps every row's swatch identical; the gap between it and
           the centred 16px glyph is what reads as the inner padding. */
        width: calc(var(--space-1) * 8);
        height: calc(var(--space-1) * 8);
        /* `--corner-sm` would land at three quarters of this box and swell it toward a
           circle — a step down keeps it a rounded rect. */
        border-radius: var(--corner-xs);
        background-color: color-mix(in oklab, var(--swatch-ink) 15%, transparent);
        /* The glyph inherits the same ink, so swatch and icon read as one mark. */
        color: var(--swatch-ink);
        line-height: 0;
    }

    .menu-swatch--sm {
        width: calc(var(--space-1) * 6);
        height: calc(var(--space-1) * 6);
    }

    .menu-swatch--round {
        border-radius: var(--corner-full);
    }

    /* A swatch inside a row that is itself inset in the list: a smaller radius that
       reads concentric with the row's own corner. */
    .menu-swatch--inset {
        border-radius: calc(var(--corner-sm) - var(--space-1_5));
    }

    /* Gray inks read heavier than colored ones, so they tint a step lighter. */
    .menu-swatch--neutral {
        background-color: color-mix(in oklab, var(--swatch-ink) 10%, transparent);
    }

    /*
      Selection is marked on the swatch: the tint deepens to roughly triple strength
      and the overlay covers the glyph. Mixed toward the raised surface rather than
      toward `transparent`, so the fill is opaque.
    */
    .menu-swatch--checked {
        background-color: color-mix(in oklab, var(--swatch-ink) 35%, var(--color-surface-raised));
    }

    .menu-swatch--neutral.menu-swatch--checked {
        background-color: color-mix(in oklab, var(--swatch-ink) 20%, var(--color-surface-raised));
    }

    .menu-swatch-state {
        position: absolute;
        inset: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: inherit;
        background-color: inherit;
        color: var(--swatch-ink);
    }
</style>
