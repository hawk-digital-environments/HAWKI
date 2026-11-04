<!--
  @component A model's provider logo seated on the shared swatch square
  (`MenuSwatch`), so the model picker's rows share the rhythm of the tool and
  assistant rows. The tint is neutral, since provider logos bring their own
  colors; a provider without a logo shows its initial on the tile rather than
  a round avatar. When `checked`, the gray tint deepens and a check covers
  the logo, the way a selected MCP tool row marks itself.

  `size="sm"` is a 24px tile for compact controls; `round` makes it a circle, for
  pill-shaped hosts such as the `ModelPicker` trigger.

  @example
  <ProviderSwatch {model} checked={model.model_id === current?.model_id}/>
-->
<script lang="ts">
    import MenuSwatch from '$plugins/core/modules/chat/components/composer/utils/MenuSwatch.svelte';
    import ProviderIcon from '$plugins/core/components/ProviderIcon.svelte';
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

<!-- Gray provider ink, so the neutral (lighter) tint strengths apply. The
     `provider-swatch` class stays: inset rows tune its radius from outside. -->
<MenuSwatch class="provider-swatch" neutral {size} shape={round ? 'round' : 'tile'} {checked}>
    <ProviderIcon name={providerName} light={model.provider?.icon_url} dark={model.provider?.icon_url_dark} size={iconSize} bare/>
</MenuSwatch>
