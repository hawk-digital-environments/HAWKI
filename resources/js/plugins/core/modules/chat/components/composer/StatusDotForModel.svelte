<!--
  @component Colored dot indicating a model's online status.
  Shows a tooltip on hover with the human-readable status label.
-->
<script lang="ts">
    import type {AiModel} from '$lib/schemas/resources/ai-models.schema.js';
    import StatusDot from '$lib/components/ui/status-dot/StatusDot.svelte';
    import {__} from '$lib/utils/translator.js';

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
