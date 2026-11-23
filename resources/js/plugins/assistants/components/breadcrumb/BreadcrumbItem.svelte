<!--
  @component One crumb in a BreadcrumbTrail: its glyph, accessible name,
  destination, current-page state and — with a `menu` — its sibling menu.
  Must render inside a trail (it reads the trail's context for the separator).

  Contract (after Meta's astryx BreadcrumbItem):
  Owns — the crumb's element (navigable button, current marker, or menu
  trigger), its glyph (icon / avatar / initial fallback), its accessible name
  (sr-only text plus a tooltip), `current` resolution, and its own leading
  separator (from the trail's context, hidden on the first item).
  Does not own — the landmark, list, label and separator default (trail);
  what activation does (the router).

  Invariants:
  - FR1 `current` is a tri-state: `true` marks this crumb, `false` opts it
    out, `undefined` auto-detects — the trail's last item becomes current
    when no sibling is explicit. Auto-detection is a mount-time DOM check:
    keyed trails remount their items, so identity changes re-resolve; trails
    that change shape in place (an item appearing later) pass explicit values.
  - FR2 `menu` takes precedence over `route`/`onclick` (dev-time warning).
  - FR3 a current crumb renders as a plain marker, never a link; a current
    crumb with a menu keeps `aria-current` on its trigger.

  Deliberate deviations from astryx: icon-only visuals — the label is sr-only
  text plus a tooltip, never visible; destinations are named routes; a glyph
  (icon/avatar) replaces their text as the visible content.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import ChevronRightIcon from '$lib/components/ui/icons/iconset/ChevronRightIcon.svelte';
    import ChevronDownIcon from '$lib/components/ui/icons/iconset/ChevronDownIcon.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte';
    import type {IconComponent} from '$lib/components/ui/icons';
    import type {AssistantAvatar} from '$plugins/assistants/types/assistant/AssistantAvatar';
    import {useBreadcrumbTrail} from './breadcrumbContext';
    import {mergeProps} from 'bits-ui';

    interface Props {
        /** Accessible name; also the tooltip text. */
        label: string;
        /** Icon glyph (alternative to `avatar`). */
        icon?: IconComponent;
        /** Assistant avatar glyph (alternative to `icon`). */
        avatar?: AssistantAvatar;
        /** Named route; renders a quiet navigable button. */
        route?: string;
        /** Activation handler, alternative to `route`. */
        onclick?: (event: MouseEvent) => void;
        /** Menu content (DropdownMenuItem composition); takes precedence over
         *  `route`/`onclick` and adds a trailing chevron. Reserved for
         *  collapsing long trails (A ▾ › … › E, on an ancestor crumb) — not
         *  for acting on the current page. */
        menu?: Snippet;
        /** The current page: `true` marks this crumb, `false` opts out,
         *  `undefined` auto-detects (the trail's last item, when no sibling
         *  is explicit). */
        current?: boolean;
    }

    const {label, icon, avatar, route, onclick, menu, current}: Props = $props();
    const router = useRouter();
    const trail = useBreadcrumbTrail();

    // One-shot dev check at init; the props are not meant to change.
    // svelte-ignore state_referenced_locally
    if (import.meta.env.DEV && menu && (route || onclick)) {
        console.warn('BreadcrumbItem: `menu` and `route`/`onclick` are mutually exclusive; the menu takes precedence.');
    }

    // Auto-detection (FR1): DOM check at mount, no child introspection.
    let li: HTMLElement | undefined = $state();
    let autoCurrent = $state(false);
    $effect(() => {
        if (current !== undefined || !li) return;
        const siblings = Array.from(li.parentElement?.children ?? []) as HTMLElement[];
        const isLast = siblings.length > 0 && siblings[siblings.length - 1] === li;
        autoCurrent = isLast && !li.parentElement!.querySelector('[aria-current="page"]');
    });

    const isCurrent = $derived(current === true || autoCurrent);

    function activate(event: MouseEvent) {
        if (route) void router.goToRoute(route);
        else onclick?.(event);
    }
</script>

{#snippet glyph()}
    {#if avatar}
        <span class="avatar" style={avatar.iconCss} aria-hidden="true">{avatar.name}</span>
    {:else if icon}
        {@const Icon = icon}
        <Icon size={16} strokeWidth={2} aria-hidden="true" />
    {:else}
        <!-- No glyph declared: the label's initial keeps the trail's shape. -->
        <span class="avatar fallback" aria-hidden="true">{label.slice(0, 1)}</span>
    {/if}
{/snippet}

<li bind:this={li} class="crumb-item">
    <span class="separator" aria-hidden="true">
        {#if trail.separator}{@render trail.separator()}{:else}<ChevronRightIcon size={12} strokeWidth={2} />{/if}
    </span>

    {#if menu}
        <DropdownMenu side="bottom" align="start">
            {#snippet trigger({props})}
                <Tooltip tooltip={label} side="bottom" sideOffset={6} delayDuration={300}>
                    {#snippet children({props: tooltipProps})}
                        <button
                            {...mergeProps(props, tooltipProps)}
                            type="button"
                            class="crumb link"
                            aria-current={isCurrent ? 'page' : undefined}
                        >
                            <span class="glyph">
                                {@render glyph()}
                            </span>
                            <span class="u-sr-only">{label}</span>
                            <ChevronDownIcon class="menu-chevron" size={10} strokeWidth={2} aria-hidden="true" />
                        </button>
                    {/snippet}
                </Tooltip>
            {/snippet}
            {@render menu()}
        </DropdownMenu>
    {:else if (route || onclick) && !isCurrent}
        <Tooltip tooltip={label} side="bottom" sideOffset={6} delayDuration={300}>
            {#snippet children({props})}
                <button
                    {...props}
                    type="button"
                    class="crumb link"
                    onclick={activate}
                >
                    <span class="glyph">
                        {@render glyph()}
                    </span>
                    <span class="u-sr-only">{label}</span>
                </button>
            {/snippet}
        </Tooltip>
    {:else}
        <!-- Not focusable: a marker must not become a dead tab stop. Tooltip
            carries the label for hover, its injected sr-only span for screen
            readers. -->
        <Tooltip tooltip={label} side="bottom" sideOffset={6} delayDuration={300} focusable={false}>
            {#snippet children({props})}
                <span {...props} class="crumb current" aria-current="page">
                    <span class="glyph">
                        {@render glyph()}
                    </span>
                </span>
            {/snippet}
        </Tooltip>
    {/if}
</li>

<style>
    .crumb-item {
        display: flex;
        align-items: center;
        gap: var(--space-1);
    }

    /* Each item carries its own leading separator; the first hides it. */
    .crumb-item:first-child .separator {
        display: none;
    }

    .separator {
        display: flex;
        align-items: center;
        color: var(--crumb-separator-color, color-mix(in oklab, var(--color-text-muted) 70%, transparent));
        user-select: none;
    }

    .separator :global(svg) {
        width: 0.75rem;
        height: 0.75rem;
    }

    .crumb {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-0_5);
        padding: 0;
        border: none;
        background: none;
        font: inherit;
    }

    /* Quiet links: colour shift only, no background pill — that affordance
       stays reserved for the nav trigger beside them. */
    .crumb.link {
        color: var(--color-text-muted);
        cursor: pointer;
        transition: color var(--duration-fast);
        border-radius: var(--corner-sm);
    }

    .crumb.link:hover,
    .crumb.link:focus-visible {
        color: var(--color-text);
        /* Over a cover (fg set) the colour shift alone is invisible: a faint
           fg tint gives the hover, like the cover's own buttons. Plain trails
           resolve this to nothing (the var is undefined there). */
        background: color-mix(in oklch, var(--crumb-fg) 16%, transparent);
    }

    .crumb.current {
        color: var(--color-text);
    }

    /* One notch under the trigger's 18px icon (20px on touch), on the row's
       shared midline. The menu chevron keeps its own smaller size. */
    .glyph {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        /* A trail over a cover sets --crumb-fg (its group pill's shade);
           plain trails leave it undefined and the glyph inherits. */
        color: var(--crumb-fg, inherit);
    }

    .glyph :global(svg) {
        width: 1rem;
        height: 1rem;
    }

    .menu-chevron {
        flex-shrink: 0;
    }

    .avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1rem;
        height: 1rem;
        border-radius: var(--corner-full);
        font-size: 0.625rem;
        font-weight: var(--font-weight-medium);
        color: oklch(100% 0 0);
    }

    .avatar.fallback {
        background: var(--color-surface-raised);
        color: var(--color-text);
    }

    @media (--bp-md-and-smaller) {
        .glyph :global(svg) {
            width: 1.125rem;
            height: 1.125rem;
        }

        .separator :global(svg) {
            width: 0.875rem;
            height: 0.875rem;
        }

        .avatar {
            width: 1.125rem;
            height: 1.125rem;
            font-size: 0.6875rem;
        }
    }
</style>
