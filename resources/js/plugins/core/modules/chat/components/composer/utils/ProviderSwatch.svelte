<!--
  @component A model's provider logo seated on the rounded square the tool and assistant
  rows use (`ToolIcon swatch`, `AssistantRow`), so the model picker shares their rhythm.
  The tint is neutral, since provider logos bring their own colors; a provider without a
  logo shows its initial on the tile rather than a round avatar. When `checked`, the
  gray tint deepens and a check covers the logo, the way a selected MCP tool row marks
  itself.

  `size="sm"` is a 24px tile for compact controls; `round` makes it a circle, for pill-shaped
  hosts such as the `ModelPicker` trigger.

  @example
  <ProviderSwatch {model} checked={model.model_id === current?.model_id}/>
-->
<script lang="ts">
    import ProviderIcon from '$plugins/core/components/ProviderIcon.svelte';
    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        model: AiModel;
        /** Deepens the gray tint and lays a check over the logo, like a selected MCP tool. */
        checked?: boolean;
        /** `md` (default) matches the tool/assistant row swatch; `sm` is for compact controls. */
        size?: 'sm' | 'md';
        /** A circle instead of a rounded square, to sit concentric in a pill. */
        round?: boolean;
    }

    const {model, checked = false, size = 'md', round = false}: Props = $props();
    const iconSize = $derived(size === 'sm' ? 14 : 16);
    const {__} = useTranslator();

    const providerName = $derived(model.provider?.name ?? __('chat.composer.modelPicker.otherProvider'));
</script>

<!-- The model's name sits right next to it, so the logo is decoration rather than content. -->
<span class={['provider-swatch', `provider-swatch--${size}`, round && 'provider-swatch--round', checked && 'provider-swatch--checked']} aria-hidden="true">
    <ProviderIcon name={providerName} light={model.provider?.icon_url} dark={model.provider?.icon_url_dark} size={iconSize} bare/>
    {#if checked}
        <span class="provider-swatch-check"><Tick02Icon size={iconSize}/></span>
    {/if}
</span>

<style>
    /* Same box and radius as `.tool-icon-swatch`, at lighter tint strengths: the gray reads
       heavier than a colored ink would. */
    .provider-swatch {
        --swatch-ink: var(--color-text-muted);

        position: relative;
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: calc(0.25rem * 8);
        height: calc(0.25rem * 8);
        border-radius: var(--corner-xs);
        background-color: color-mix(in oklab, var(--swatch-ink) 10%, transparent);
        line-height: 0;
    }

    .provider-swatch--sm {
        width: calc(0.25rem * 6);
        height: calc(0.25rem * 6);
    }

    .provider-swatch--round {
        border-radius: var(--corner-full);
    }

    /* Selected like a gray MCP tool row, a step lighter than `.tool-icon-swatch--checked`. */
    .provider-swatch--checked {
        background-color: color-mix(in oklab, var(--swatch-ink) 20%, var(--color-surface-raised));
    }

    .provider-swatch-check {
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
