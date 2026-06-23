<script lang="ts">

    import {Bot} from '@lucide/svelte';
    import BorderBeam from '$lib/components/ui/border-beam/BorderBeam.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {aiHandleStore} from '$lib/stores/AiHandleStore.svelte.js';
    import {__} from '$lib/utils/translator.js';
    import type {ComponentProps} from 'svelte';

    const composerContext = useComposerContext();

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
            iconLeft={Bot}
            variant="ghost"
            tooltip={assistantButtonTooltip}
            highlight={isActive}
            disabled={composerContext.sendStatus?.sending}
            onclick={handleAssistantButtonClick}
        />
    </BorderBeam>
{/if}
