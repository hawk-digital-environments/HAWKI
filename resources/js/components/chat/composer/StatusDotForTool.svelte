<!--
  @component Colored dot indicating a model's online status.
  Shows a tooltip on hover with the human-readable status label.
-->
<script lang="ts">
    import StatusDot from '$lib/components/ui/status-dot/StatusDot.svelte';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {__} from '$lib/utils/translator.js';
    import type {AiToolOrCapability} from '$lib/stores/aiToolStoreData.js';
    import type {AiTool} from '$lib/schemas/resources/ai-tools.schema.js';

    const composerContext = useComposerContext();

    interface Props {
        /** The tool whose status to show. */
        tool: AiTool | AiToolOrCapability;
        /** If false, the tool status will show up as "not-supported" by the current model (overriding the actual tool status). */
        supported?: boolean;
        /** Visual size of the dot. */
        size?: 'sm' | 'md';
        /** If true, the human-readable status label will be shown next to the dot. */
        showLabel?: boolean;
        /** An optional suffix to add to all tooltip texts, e.g. to indicate the context for the status */
        tooltipSuffix?: string;
    }

    const {tool, size, supported, tooltipSuffix, showLabel = false}: Props = $props();

    const status = $derived.by(() => {
        if (supported === false && tool.status !== 'offline') {
            return 'unknown';
        }
        return tool.status;
    });

    const unknownLabel = $derived.by(() => {
        if (supported === false) {
            return __('chat.composer.statusDot.tool.notSupportedLabel', {model: composerContext.model?.current.label ?? ''});
        }
        return showLabel ? __('chat.composer.statusDot.unknownAvailability') : undefined;
    });

    const unknownTooltip = $derived.by(() => {
        if (supported === false) {
            return __('chat.composer.statusDot.tool.notSupportedTooltip', {model: composerContext.model?.current.label ?? ''});
        }
        return __('chat.composer.statusDot.unknownTooltip');
    });

    const wrappedTooltipSuffix = $derived.by(() => tooltipSuffix ? ` | ${tooltipSuffix}` : '');
</script>

<StatusDot
    status={status}
    size={size}
    labelOnline={showLabel ? __('chat.composer.statusDot.onlineLabel') : undefined}
    tooltipOnline={__('chat.composer.statusDot.onlineTooltip') + wrappedTooltipSuffix}
    labelUnknown={showLabel ? unknownLabel : undefined}
    tooltipUnknown={unknownTooltip + wrappedTooltipSuffix}
    labelOffline={showLabel ? __('chat.composer.statusDot.tool.offlineLabel') : undefined}
    tooltipOffline={__('chat.composer.statusDot.offlineTooltip') + wrappedTooltipSuffix}
/>
