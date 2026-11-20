<!--
  @component Inline banner for a titled message with an optional leading icon, e.g. a validation
  summary or a destructive-action warning. Renders nothing when both `title` and `description`
  are omitted except the icon.

  Exposed as a live region (`role="status"`, or `role="alert"` for the `error` tone) so
  dynamically inserted alerts are announced, with a visually hidden "Note:"/"Warning:" prefix
  so the severity isn't conveyed by color alone.

  Alerts are flat fills without an outline. `size="small"` is the low-key form for a
  contextual caveat above a form section (e.g. "changes here affect the review"): compact
  type on a lighter fill.

  `tone` colors the icon; the colored tones (info, warning, error) also tint the fill.
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
        /** Size variant of the Alert */
        size?: Size;
        /** Background variant; defaults to the lighter fill for the small size. Replaced by a colored tone's tint. */
        surface?: "surface" | "surface-light" | "surface-raised" | "surface-inverted"
        /** Colors the icon; info/warning/error also tint the fill. The text keeps its color. */
        tone?: "neutral" | "info" | "warning" | "error";
    }

    const { title, description, icon: Icon, size = "default", surface, tone }: Props = $props();

    const sizeMapping = {
        "small": ["sm", "xs"],
        "default": ["xl", "base"],
        "large": ["2xl", "xl"],
    } as const;

    const iconSizeMapping = {
        "small": 14,
        "default": 24,
        "large": 48,
    } as const;

    const fill = $derived(surface ?? (size === 'small' ? 'surface-light' : 'surface'));
    const isWarning = $derived(tone === 'warning' || tone === 'error');
</script>

<div
    class="alert-card size-{size}"
    data-tone={tone}
    style="--bg-color: var(--color-{fill}); --first-line-size: var(--font-size-{sizeMapping[size][title ? 0 : 1]})"
    role={tone === 'error' ? 'alert' : 'status'}
>
    {#if Icon}
        <div class="alert-icon" aria-hidden="true">
            <Icon size={iconSizeMapping[size]} />
        </div>
    {/if}
    <div class="alert-content">
        <span class="u-sr-only">{isWarning ? __('ui.alert.warningPrefix') : __('ui.alert.notePrefix')}</span>
        {#if title}
            <Txt size={sizeMapping[size][0]} weight="medium">{title}</Txt>
        {/if}
        {#if description}
            <Txt size={sizeMapping[size][1]}>{description}</Txt>
        {/if}
    </div>
</div>

<style>
    .alert-card {
        gap: var(--space-1_5);
        border-radius: var(--corner-md);
        display: flex;
        padding: var(--space-2);
        background-color: var(--bg-color);
    }

    .alert-card.size-small {
        gap: var(--space-2);
        padding: var(--space-2_5) var(--space-4);
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
</style>


