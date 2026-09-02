<!--
  @component The caret-anchored `@` mention list: the popup that appears while the user is
  typing a mention in `ComposerTextarea`, showing the assistants whose name or handle match
  what has been typed so far.

  The assistant already tagged in the message is checked, the same way the `/` tool popup
  marks the tools that are on — so the list says what the current state is, not just what
  can be picked.

  Same rows as the `@` button menu (`AssistantRow`), wrapped in the shared
  `CaretMentionPopup`, which owns the chrome, the caret positioning and the keyboard
  scrolling — see there for the listbox semantics this popup takes part in.

  ## Usage
  ```svelte
  <AssistantMentionPopup
      assistants={filteredAssistants}
      caret={caretRect}
      activeIndex={mentionIndex}
      onSelect={insertMention}/>
  ```
-->
<script module lang="ts">
    /** DOM id of the option at `index`, so the textarea can reference it via
     *  `aria-activedescendant`. */
    export function assistantMentionOptionId(index: number): string {
        return `assistant-mention-option-${index}`;
    }
</script>
<script lang="ts">
    import type {AiAssistantHandle} from '$plugins/core/stores/AiHandleStore.svelte.js';
    import type {CaretRect} from '$plugins/core/modules/chat/components/composer/utils/textareaCaret.js';
    import AssistantRow from '$plugins/core/modules/chat/components/composer/AssistantRow.svelte';
    import CaretMentionPopup from '$plugins/core/modules/chat/components/composer/CaretMentionPopup.svelte';
    import {getAssistantAppearance} from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    interface Props {
        /** The assistants matching the typed query, in display order. Never empty —
         *  `ComposerTextarea` closes the popup instead of rendering an empty list. */
        assistants: AiAssistantHandle[];
        /** Viewport-relative caret position the popup anchors to. */
        caret: CaretRect;
        /** Index of the highlighted assistant, driven by the textarea's arrow keys and by
         *  hovering a row. */
        activeIndex: number;
        /** Called when the pointer moves over a row, so hovering moves the highlight —
         *  otherwise Enter and a click could target two different assistants. */
        onActivate: (index: number) => void;
        /** Called with the assistant to insert (click or Enter/Tab in the textarea). */
        onSelect: (assistant: AiAssistantHandle) => void;
        /** Handle of the assistant currently tagged in the message, if any — that row is
         *  rendered checked. A message addresses at most one assistant. */
        activeHandle?: string | null;
    }

    const {assistants, caret, activeIndex, onActivate, onSelect, activeHandle = null}: Props = $props();
</script>

<CaretMentionPopup
    items={assistants}
    key={(assistant) => assistant.id}
    optionId={assistantMentionOptionId}
    label={__('chat.composer.assistantMenu.title')}
    caret={caret}
    activeIndex={activeIndex}
    onActivate={onActivate}
    onSelect={onSelect}>
    {#snippet row(assistant)}
        <AssistantRow
            assistant={assistant}
            emoji={getAssistantAppearance(assistant.id).emoji}
            colors={getAssistantAppearance(assistant.id).colors}
            checked={assistant.handle === activeHandle}/>
    {/snippet}
</CaretMentionPopup>
