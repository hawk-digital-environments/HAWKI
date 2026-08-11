<!--
  @component Thin wrapper around the `components/ui/status-dot` primitive
  that fills in model-specific translated labels/tooltips for the `online` /
  `offline` / `unknown` states from `AiModel.status`. Use this (rather than
  `StatusDot` directly) anywhere a model's availability needs to be shown —
  it keeps the wording consistent (e.g. in `ModelPicker`'s dropdown list).

  @example
  ```svelte
  {#each models as m}
      <StatusDotForModel model={m}/>
  {/each}
  ```
-->
<script lang="ts">
    import StatusDot from '$lib/components/ui/status-dot/StatusDot.svelte';
    import type {AiModel} from '$lib/plugins/core/schemas/resources/ai-models.schema.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    interface Props {
        /** The model whose status to show. */
        model: AiModel;
        /** Visual size of the dot. */
        size?: 'sm' | 'md';
        /** If true, the human-readable status label will be shown next to the dot. */
        showLabel?: boolean;
    }

    const {model, size, showLabel = false}: Props = $props();
</script>

<StatusDot
    status={model.status}
    size={size}
    labelOnline={showLabel ? __('chat.composer.statusDot.onlineLabel') : undefined}
    tooltipOnline={__('chat.composer.statusDot.onlineTooltip')}
    labelUnknown={showLabel ? __('chat.composer.statusDot.unknownLabel') : undefined}
    tooltipUnknown={__('chat.composer.statusDot.unknownTooltip')}
    labelOffline={showLabel ? __('chat.composer.statusDot.offlineLabel') : undefined}
    tooltipOffline={__('chat.composer.statusDot.offlineTooltip')}
/>
