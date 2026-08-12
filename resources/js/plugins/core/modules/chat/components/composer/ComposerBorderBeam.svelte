<!--
  @component Wraps the whole composer card in a `BorderBeam` (animated glowing
  border) that lights up while a send is active, i.e. it is the "the AI is
  working" affordance around the entire composer (as opposed to
  `ComposerAssistantButton`'s beam, which only decorates the assistant-toggle
  button). No props besides the wrapped content; activation is derived purely
  from `ComposerContext`:

  - `forcedActive` always forces the beam on.
  - In `room` context, the beam stays off unless the message actually
    contains an `@hawki` handle (a private room message being sent shouldn't
    flash the AI beam).
  - Otherwise mirrors `composerContext.sendStatus?.active`.

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
    import {type Snippet} from 'svelte';

    const composerContext = useComposerContext();

    interface Props {
        /** The composer card content to render inside the animated border. */
        children: Snippet;
    }

    const {children}: Props = $props();

    const isActive = $derived.by(() => {
        if (composerContext.forcedActive || composerContext.backgroundActive) {
            return true;
        }

        // When sending a private message without chatting to the ai
        // We don't want to show the beam, as it would only lead to weird flashing.
        if (composerContext.type === 'room' && !composerContext.containsAiHandle) {
            return false;
        }

        return composerContext.sendStatus?.active ?? false;
    });
</script>
<BorderBeam
    size="md"
    active={isActive}
    duration={3}
>
    {@render children()}
</BorderBeam>
