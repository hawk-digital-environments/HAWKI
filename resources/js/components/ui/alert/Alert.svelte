<!--
  @component Inline banner for a titled message with an optional leading icon, e.g. a validation
  summary or a destructive-action warning. Renders nothing when both `title` and `description`
  are omitted except the icon.

  Exposed as a live region (`role="status"`, or `role="alert"` for the `destructive` variant)
  so dynamically inserted alerts are announced, with a visually hidden "Note:"/"Warning:" prefix
  so the severity isn't conveyed by color alone.
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
    }

    const { title, description, icon: Icon, variant = "default", size = "default", surface = "surface" }: Props = $props();

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
</script>

<div
    class="alert-card variant-{variant}"
    style="--bg-color: var(--color-{surface}); --first-line-size: var(--font-size-{sizeMapping[size][title ? 0 : 1]})"
    role={variant === 'destructive' ? 'alert' : 'status'}
>
    {#if Icon}
        <div class="alert-icon" aria-hidden="true">
            <Icon size={iconSizeMapping[size]} />
        </div>
    {/if}
    <div class="alert-content">
        <span class="u-sr-only">{variant === 'destructive' ? __('ui.alert.warningPrefix') : __('ui.alert.notePrefix')}</span>
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
        border: var(--border);
        border-radius: var(--corner-md);
        display: flex;
        padding: var(--space-2);
        background-color: var(--bg-color);
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
</style>


