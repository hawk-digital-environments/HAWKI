<!--
  @component Status dot for AI tools/capabilities in the assistant builder —
  the builder-side counterpart of the composer's `StatusDotForTool`, minus its
  `ComposerContext` dependency (that hook throws outside the composer, and the
  builder pages are no composer).

  Semantics mirror `StatusDotForTool`: the dot reflects the tool's own
  online/offline status, and `supported={false}` overrides it to the "unknown"
  visual with a "not supported by <model>" label/tooltip — pass the builder's
  currently selected model via `model`. Wording reuses the same
  `chat.composer.statusDot.*` translation keys (as `StatusDotForModel`, already
  used in the builder, does too).

  @example
  ```svelte
  <ToolStatusDot tool={capability} supported={isAvailable} model={currentModel}/>
  ```
-->
<script lang="ts">
    import StatusDot from '$lib/components/ui/status-dot/StatusDot.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {AiTool} from '$plugins/core/schemas/resources/ai-tools.schema.js';
    import type {AiToolOrCapability} from '$plugins/core/stores/aiToolStoreData.js';
    import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';

    const {__} = useTranslator();

    interface Props {
        /** The tool (or capability wrapper) whose status to show. */
        tool: AiTool | AiToolOrCapability;
        /** If false, the dot shows the "not supported by the selected model" state instead of the tool status. */
        supported?: boolean;
        /** The builder's currently selected model — used for the not-supported label/tooltip. */
        model?: AiModel | null | undefined;
        /** Visual size of the dot. */
        size?: 'sm' | 'md';
        /** If true, the human-readable status label is shown next to the dot. */
        showLabel?: boolean;
        /** Set to false when the dot sits inside another focusable control, so it does not add a tab stop. */
        focusable?: boolean;
    }

    const {tool, supported, model, size, showLabel = false, focusable = true}: Props = $props();

    const status = $derived.by(() => {
        if (supported === false && tool.status !== 'offline') {
            return 'unknown';
        }
        return tool.status;
    });

    const unknownLabel = $derived.by(() => {
        if (supported === false) {
            return __('chat.composer.statusDot.tool.notSupportedLabel', {model: model?.label ?? ''});
        }
        return showLabel ? __('chat.composer.statusDot.unknownAvailability') : undefined;
    });

    const unknownTooltip = $derived.by(() => {
        if (supported === false) {
            return __('chat.composer.statusDot.tool.notSupportedTooltip', {model: model?.label ?? ''});
        }
        return __('chat.composer.statusDot.unknownTooltip');
    });
</script>

<StatusDot
    status={status}
    size={size}
    {focusable}
    labelOnline={showLabel ? __('chat.composer.statusDot.onlineLabel') : undefined}
    tooltipOnline={__('chat.composer.statusDot.onlineTooltip')}
    labelUnknown={showLabel ? unknownLabel : undefined}
    tooltipUnknown={unknownTooltip}
    labelOffline={showLabel ? __('chat.composer.statusDot.tool.offlineLabel') : undefined}
    tooltipOffline={__('chat.composer.statusDot.offlineTooltip')}
/>
