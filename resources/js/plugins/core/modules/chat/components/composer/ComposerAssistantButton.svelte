<!--
  @component Room-chat-only toggle that adds/removes the `@hawki` handle from
  the composer message, i.e. it decides whether the message is addressed to
  the AI assistant or is a plain human-to-human room message. Renders nothing
  in `aiConv` context (there, every message already goes to the AI).

  Wraps its button in a `BorderBeam` that periodically pulses ("burst of
  interest") every 30-60s to draw attention to the assistant when it's not
  active, and glows steadily while active. No props — reads/writes
  `ComposerContext` directly (toggles the `@hawki` handle via
  `composerContext.addHandleToMessage` / `messageWithoutHandles`).

  @example
  ```svelte
  // placed at the start of the textarea row
  <ComposerAssistantButton/>
  <Textarea bind:value={composerContext.message} .../>
  ```
-->
<script lang="ts">

    import BorderBeam from '$lib/components/ui/border-beam/BorderBeam.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import type {ComponentProps} from 'svelte';
    import BotIcon from '$lib/components/ui/icons/iconset/BotIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';

    const composerContext = useComposerContext();
    const {__} = useTranslator();
    const aiHandleStore = useStore('ai-handle');

    let showsBurstOfInterest = $state(false);

    const isActive = $derived.by(() => composerContext.guard.showsAiUiElements);

    const assistantButtonTooltip = $derived.by(() => {
        if (isActive) {
            return __('chat.composer.assistantButtonTooltip.removeHandle');
        }
        return __('chat.composer.assistantButtonTooltip.addHandle');
    });

    const beamProps = $derived.by((): Partial<ComponentProps<typeof BorderBeam>> => {
        if (isActive) {
            return {strength: 1, brightness: 1.5, size: 'md', duration: 5};
        } else if (showsBurstOfInterest) {
            return {strength: 0.5, brightness: 2, size: 'sm', duration: 3};
        }
        return {active: false, strength: 0.5, brightness: 2, size: 'sm', duration: 3};
    });

    function handleAssistantButtonClick() {
        if (isActive) {
            composerContext.message = composerContext.messageWithoutHandles;
        } else {
            composerContext.addHandleToMessage(aiHandleStore.hawkiHandle);
        }
    }

    // Sometimes, set showsBurstOfInterest to true for 1-2 seconds, before turning it off again.
    // This should trigger the border beam to do a little "burst of interest" animation, drawing the user's attention to the assistant button, without being too distracting.
    // Between every burst, there should be a random delay of between 30 and 60 seconds.
    $effect(() => {
        let timeout: NodeJS.Timeout;

        const queueNextBurstOfInterest = () => {
            timeout = setTimeout(triggerBurstOfInterest, Math.random() * 30000 + 30000);
        };

        function triggerBurstOfInterest() {
            showsBurstOfInterest = true;
            timeout = setTimeout(() => {
                showsBurstOfInterest = false;
                queueNextBurstOfInterest();
            }, 4000);
        }

        queueNextBurstOfInterest();
        return () => clearTimeout(timeout);
    });
</script>

{#if composerContext.type === 'room'}
    <BorderBeam {...beamProps}>
        <ButtonWithTooltip
            iconLeft={BotIcon}
            variant="ghost"
            tooltip={assistantButtonTooltip}
            highlight={isActive}
            disabled={composerContext.sendStatus?.sending}
            onclick={handleAssistantButtonClick}
        />
    </BorderBeam>
{/if}
