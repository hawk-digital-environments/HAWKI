<!--
  @component Inline banner for a titled message with an optional leading icon, e.g. a validation
  summary or a destructive-action warning. Renders nothing when both `title` and `description`
  are omitted except the icon.

  Exposed as a live region (`role="status"`, or `role="alert"` for the `destructive` variant)
  so dynamically inserted alerts are announced, with a visually hidden "Note:"/"Warning:" prefix
  so the severity isn't conveyed by color alone.

  `quiet` is the low-key form for a contextual caveat above a form section
  (e.g. "changes here affect the review"): no outline, a flat light fill, and
  one fixed compact type scale (`size` and `surface` are ignored).

  `tone` colors the icon; the colored tones (info, warning, error) also tint
  the fill and, on the outlined form, the border.
-->
<script lang="ts">
    import type { IconComponent } from '$lib/components/ui/icons';
    import Txt from '$lib/components/ui/Txt.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    type Size = "small" | "default" | "large";

    interface Props {
        /** Heading text of the Alert */
        title?: string;
        /** Description text of the Alert */
        description?: string;
        /** Leading icon of the Alert */
        icon?: IconComponent;
        /** Visual style variant of the Alert */
        variant?: "default" | "destructive";
        /** Size variant of the Alert */
        size?: Size;
        /** Background variant */
        surface?: "surface" | "surface-raised" | "surface-inverted"
        /** Borderless, flat inline hint instead of the outlined banner. */
        quiet?: boolean;
        /** Colors the icon; info/warning/error also tint the fill and the outline. The text keeps its color. */
        tone?: "neutral" | "info" | "warning" | "error";
    }

    const { title, description, icon: Icon, variant = "default", size = "default", surface = "surface", quiet = false, tone }: Props = $props();

    const sizeMapping = {
        "small": ["xs", "xxs"],
        "default": ["xl", "base"],
        "large": ["2xl", "xl"],
    } as const;

    const iconSizeMapping = {
        "small": 14,
        "default": 24,
        "large": 48,
    } as const;

    const textSizes = $derived(quiet ? (["sm", "xs"] as const) : sizeMapping[size]);
    const iconSize = $derived(quiet ? 14 : iconSizeMapping[size]);
    const isWarning = $derived(variant === 'destructive' || tone === 'warning' || tone === 'error');
</script>

<div
    class="alert-card variant-{variant} size-{size}"
    class:quiet
    data-tone={tone}
    style="--bg-color: var(--color-{surface}); --first-line-size: var(--font-size-{textSizes[title ? 0 : 1]})"
    role={variant === 'destructive' ? 'alert' : 'status'}
>
    {#if Icon}
        <div class="alert-icon" aria-hidden="true">
            <Icon size={iconSize} />
        </div>
    {/if}
    <div class="alert-content">
        <span class="u-sr-only">{isWarning ? __('ui.alert.warningPrefix') : __('ui.alert.notePrefix')}</span>
        {#if title}
            <Txt size={textSizes[0]} weight="medium">{title}</Txt>
        {/if}
        {#if description}
            <Txt size={textSizes[1]}>{description}</Txt>
        {/if}
    </div>
</div>

<style>
    .alert-card {
        gap: var(--space-1_5);
        border: var(--border);
        border-radius: var(--corner-md);
        display: flex;
        padding: var(--space-2);
        background-color: var(--bg-color);
    }

    /* The small alert is shorter than a form control; the shared radius
       would round it into a pill, so it steps down to stay visually equal. */
    .alert-card.size-small {
        border-radius: var(--corner-sm);
    }

    .alert-card.quiet {
        gap: var(--space-2);
        padding: var(--space-2_5) var(--space-4);
        border: none;
        border-radius: var(--corner-md);
        background-color: var(--color-surface-light);
    }

    .alert-icon {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        /* As tall as the first text line, so the icon centers on it. */
        height: calc(var(--first-line-size) * var(--line-height-normal));
    }

    .alert-content {
        display: flex;
        flex-direction: column;
        gap: var(--space-0_5, 0.125rem);
    }

    .variant-destructive {
        color: var(--color-error)
    }

    [data-tone='neutral'] .alert-icon { color: var(--color-text-muted); }
    [data-tone='info'] { --tone-color: var(--color-info); }
    [data-tone='warning'] { --tone-color: var(--color-warning); }
    [data-tone='error'] { --tone-color: var(--color-error); }

    /* Icon in the pure status color — darkening it toward the text color
       turned the warning amber into brown. */
    :is([data-tone='info'], [data-tone='warning'], [data-tone='error']) .alert-icon {
        color: var(--tone-color);
    }

    /* A colored tone washes the fill (neutral stays grey), replacing
       `surface`. Built from the tone's hue at a fixed high lightness and
       chroma instead of diluting the status color: a diluted dark color
       reads beige, a light saturated one reads clean. */
    .alert-card:is([data-tone='info'], [data-tone='warning'], [data-tone='error']) {
        --tint-l: 96.5%;
        background-color: oklch(from var(--tone-color) var(--tint-l) 0.03 h);
    }

    :global(html.darkMode) .alert-card {
        --tint-l: 28%;
    }

    /* The outlined form draws its border in the tone as well. */
    .alert-card:not(.quiet):is([data-tone='info'], [data-tone='warning'], [data-tone='error']) {
        border-color: color-mix(in oklab, var(--tone-color) 35%, transparent);
    }
</style>


