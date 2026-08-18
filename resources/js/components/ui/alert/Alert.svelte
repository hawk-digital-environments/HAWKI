<!--
  @component Inline banner for a titled message with an optional leading icon, e.g. a validation
  summary or a destructive-action warning. Purely presentational — renders nothing when both
  `title` and `description` are omitted except the icon.
-->
<script lang="ts">
    import type { IconComponent } from '../icons/index.js';
    import Txt from '../Txt.svelte';

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

<div class="alert-card variant-{variant}" style="--bg-color: var(--color-{surface})">
    {#if Icon}
        <div>
            <Icon size={iconSizeMapping[size]} />
        </div>
    {/if}
    <div class="alert-content">
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

    .alert-content {
        display: flex;
    }

    .variant-destructive {
        color: var(--color-error)
    }
</style>


