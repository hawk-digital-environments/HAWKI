<!--
  @component The caret-anchored `/` tool list: the popup that appears while the user is
  typing a slash command in `ComposerTextarea`, showing the tools and capabilities whose
  name matches what has been typed so far. Picking one toggles it in
  `composerContext.tools` — the same switch the `ToolMenu` button offers, reachable
  without leaving the keyboard.

  Rows come from `ToolRow` and are wrapped in the shared `CaretMentionPopup`, which owns
  the chrome, the caret positioning and the keyboard scrolling — see there for the listbox
  semantics this popup takes part in.

  ## Usage
  ```svelte
  <ToolMentionPopup
      entries={matchingTools}
      caret={caretRect}
      activeIndex={mentionIndex}
      onSelect={applyToolMention}/>
  ```
-->
<script module lang="ts">
    /** One selectable tool row: the tool itself plus whether it is currently enabled. */
    export interface ToolMentionEntry {
        tool: AiToolOrCapability;
        active: boolean;
    }

    /** DOM id of the option at `index`, so the textarea can reference it via
     *  `aria-activedescendant`. */
    export function toolMentionOptionId(index: number): string {
        return `tool-mention-option-${index}`;
    }
</script>
<script lang="ts">
    import type {AiToolOrCapability} from '$plugins/core/stores/aiToolStoreData.js';
    import type {CaretRect} from '$plugins/core/modules/chat/components/composer/utils/textareaCaret.js';
    import CaretMentionPopup from '$plugins/core/modules/chat/components/composer/CaretMentionPopup.svelte';
    import ToolRow from '$plugins/core/modules/chat/components/composer/ToolRow.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    interface Props {
        /** The tools matching the typed query, in display order. Never empty —
         *  `ComposerTextarea` closes the popup instead of rendering an empty list. */
        entries: ToolMentionEntry[];
        /** Viewport-relative caret position the popup anchors to. */
        caret: CaretRect;
        /** Index of the highlighted tool, driven by the textarea's arrow keys and by
         *  hovering a row. */
        activeIndex: number;
        /** Called when the pointer moves over a row, so hovering moves the highlight. */
        onActivate: (index: number) => void;
        /** Called with the tool to toggle (click or Enter/Tab in the textarea). */
        onSelect: (entry: ToolMentionEntry) => void;
    }

    const {entries, caret, activeIndex, onActivate, onSelect}: Props = $props();
</script>

<CaretMentionPopup
    items={entries}
    key={(entry) => entry.tool.name}
    optionId={toolMentionOptionId}
    label={__('chat.composer.toolMenu.manageTools')}
    caret={caret}
    activeIndex={activeIndex}
    onActivate={onActivate}
    onSelect={onSelect}>
    {#snippet row(entry)}
        <ToolRow tool={entry.tool} checked={entry.active}/>
    {/snippet}
</CaretMentionPopup>
