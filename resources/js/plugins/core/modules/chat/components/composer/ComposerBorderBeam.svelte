<!--
  @component Wraps the whole composer card in a `BorderBeam` (animated glowing
  border) that lights up while a send is active, i.e. it is the "the AI is
  working" affordance around the entire composer (as opposed to `MentionPills`'
  beams, which decorate the individual `@handle`s typed into the message). No
  props besides the wrapped content; activation is derived purely from
  `ComposerContext`:

  - `forcedActive` always forces the beam on.
  - In `room` context, the beam stays off unless the message actually
    contains an `@hawki` handle (a private room message being sent shouldn't
    flash the AI beam).
  - Otherwise mirrors `composerContext.sendStatus?.active`.

  The glow takes the colors of the assistant the message is addressed to, matching that
  assistant's chip — so the composer lights up in the colors of whoever is answering.

  @example
  ```svelte
  <ComposerBorderBeam>
      <ComposerFocusWrap ...>
          // rest of the composer card
      </ComposerFocusWrap>
  </ComposerBorderBeam>
  ```
-->
<script lang="ts">

    import BorderBeam from '$lib/components/ui/border-beam/BorderBeam.svelte';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {DEFAULT_ASSISTANT_COLORS} from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {type Snippet} from 'svelte';

    const composerContext = useComposerContext();
    const aiHandleStore = useStore('ai-handle');

    interface Props {
        /** The composer card content to render inside the animated border. */
        children: Snippet;
    }

    const {children}: Props = $props();

    const isActive = $derived.by(() => {
        if (composerContext.forcedActive) {
            return true;
        }

        // When sending a private message without chatting to the ai
        // We don't want to show the beam, as it would only lead to weird flashing.
        if (composerContext.type === 'room' && !composerContext.containsAiHandle) {
            return false;
        }

        return composerContext.sendStatus?.active ?? false;
    });

    // A message addresses at most one assistant, so the first handle is the whole answer.
    // With none — every AI-chat message, where HAWKI is implicit — the beam keeps the
    // authored brand palette it has always had.
    const colors = $derived.by(() => {
        const handle = composerContext.handlesInMessage[0];
        const assistant = aiHandleStore.assistants.find(candidate => candidate.handle === handle);
        if (!assistant) {
            return undefined;
        }
        return assistant?.appearance?.colors ?? DEFAULT_ASSISTANT_COLORS;
    });
</script>
<BorderBeam
    size="md"
    active={isActive}
    duration={3}
    {colors}
>
    {@render children()}
</BorderBeam>
