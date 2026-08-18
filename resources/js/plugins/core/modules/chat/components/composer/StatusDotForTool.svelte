<!--
  @component Thin wrapper around the `components/ui/status-dot` primitive
  for AI tools/capabilities. Like `StatusDotForModel`, it fills in
  tool-specific translated labels/tooltips for `online`/`offline`/`unknown`.
  Additionally understands the "tool exists but isn't supported by the
  currently selected model" case via `supported={false}` — in that case the
  dot is forced to the `unknown` visual regardless of the tool's own status,
  and the label/tooltip explain that it's a model incompatibility rather than
  the tool being down. Used e.g. in `ToolMenuListItem`'s per-tool info button.

  @example
  ```svelte
  // plain tool status
  <StatusDotForTool tool={someTool}/>
  ```

  @example
  ```svelte
  // tool exists but the active model doesn't support it
  <StatusDotForTool
      tool={entry.tool}
      supported={entry.available}
      tooltipSuffix={__('chat.composer.toolMenu.clickForInfo')}
  />
  ```
-->
<script lang="ts">
    import {StatusDot} from '@hawk-hhg/hawki-svelte-components';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {AiTool} from '$plugins/core/schemas/resources/ai-tools.schema.js';
    import type {AiToolOrCapability} from '$plugins/core/stores/aiToolStoreData.js';

    const composerContext = useComposerContext();
    const {__} = useTranslator();

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
