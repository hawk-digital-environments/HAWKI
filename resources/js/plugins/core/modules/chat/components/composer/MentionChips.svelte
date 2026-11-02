<!--
  @component Renders the assistants tagged in the current message as real chips, sitting in
  the composer's top row next to the model picker — the replacement for the old dedicated
  `@hawki` button, and for the earlier attempt at drawing pills behind the textarea's text.

  The message text remains the source of truth (`ComposerContext.handlesInMessage`); the
  textarea just doesn't show the handle tokens, `ComposerTextarea` keeps them out of the
  text it displays. This component only resolves handles to assistants; each `MentionChip`
  owns its own reveal-then-pill animation, and removing one strips that handle from the
  message. The AI itself (the configured mention handle, e.g. `@hawki`) is deliberately
  chip-less — addressing it is what makes the model/settings controls appear, so a chip
  next to them would say the same thing twice.

  What this component owns is the *row* half of the motion: a tag unrolls its width on the
  way in and collapses it on the way out, so the model picker sitting next to it glides
  aside instead of being snapped sideways the moment a handle enters the message. The
  unroll runs ahead of the handle's reveal and hands off to the pill, so tagging someone
  reads as one continuous move rather than three.

  ## Usage
  Rendered by `ChatComposer` in the top row's left group, after `ModelPicker`. That group is
  only mounted while `guard.showsAiUiElements` is true — which in a room is exactly when the
  message carries a handle, i.e. whenever there is a chip to show:
  ```svelte
  <div class="chat-composer-left">
      <ModelPicker/>
      <MentionChips/>
  </div>
  ```
-->
<script lang="ts">
    import MentionChip from '$plugins/core/modules/chat/components/composer/MentionChip.svelte';
    import {chipPop} from '$lib/utils/transitions/chipPop';
    import {flip} from 'svelte/animate';
    import {cubicOut} from 'svelte/easing';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {defaultAssistantAppearance} from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';

    const composerContext = useComposerContext();
    const aiHandleStore = useStore('ai-handle');

    // One chip per handle in the message, resolved back to the assistant it belongs to so
    // the chip can show that assistant's icon, name and color. The AI itself (the
    // configured mention handle) never gets one: addressing it is signaled by the
    // model/settings controls appearing (see `ChatComposer`'s showAiMentionHandleControls),
    // and its checked row in the assistant menu is where it gets untagged.
    const chips = $derived.by(() => {
        return composerContext.handlesInMessage
            .filter(handle => handle !== aiHandleStore.hawkiHandle)
            .map(handle => {
                const assistant = aiHandleStore.assistants.find(candidate => candidate.handle === handle);
                const appearance = assistant?.appearance
                    ?? defaultAssistantAppearance(assistant ? assistant.label : handle.slice(1));
                return {
                    handle,
                    emoji: appearance.icon,
                    colors: appearance.colors,
                    label: assistant ? assistant.label : handle
                };
            });
    });

    // The box is open before the handle has finished spelling itself out, so the reveal is
    // never clipped and the pill's own arrival (which starts around 95ms in) lands after
    // the unroll rather than on top of it. No overshoot and barely any scale: the chip
    // makes its entrance on the inside, and a bouncing row would only fight it.
    const enter = {duration: 95, scaleFrom: 0.94, overshoot: false} as const;
    const leave = {direction: 'out', scaleFrom: 0.94} as const;
</script>

{#each chips as chip (chip.handle)}
    <!-- The wrapper carries the row motion, so `MentionChip` keeps a plain button as its
         root and stays free to animate its own insides on its own clock. -->
    <span
        class="mention-chip-slot"
        in:chipPop={enter}
        out:chipPop={leave}
        animate:flip={{duration: 85, easing: cubicOut}}>
        <MentionChip
            handle={chip.handle}
            label={chip.label}
            emoji={chip.emoji}
            colors={chip.colors}
            disabled={composerContext.guard.disablesFeature('assistants')}
            onRemove={() => composerContext.removeHandleFromMessage(chip.handle)}/>
    </span>
{/each}

<style>
    /* Growing out of the start edge keeps a new tag unrolling into the row instead of
       pushing back into whatever precedes it. */
    .mention-chip-slot {
        display: inline-flex;
        flex-shrink: 0;
        transform-origin: left center;
    }

    @media (prefers-reduced-motion: reduce) {
        .mention-chip-slot {
            transform: none !important;
        }
    }
</style>
