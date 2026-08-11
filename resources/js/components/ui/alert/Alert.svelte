<script lang="ts">
    import type { IconComponent } from '$lib/components/ui/icons';
    import Txt from '$lib/components/ui/Txt.svelte';

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
</script>

<div class="alert-card variant-{variant}" style="--bg-color: var(--color-{surface})">
    {#if Icon}
        <Icon />
    {/if}
    <div>
        <Txt size={sizeMapping[size][0]} weight="medium">{title}</Txt>
        <Txt size={sizeMapping[size][1]}>{description}</Txt>
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

    .variant-destructive {
        color: var(--color-error)
    }
</style>


