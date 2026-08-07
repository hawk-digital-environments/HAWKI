<script lang="ts">

    import BorderBeam from '$lib/components/ui/border-beam/BorderBeam.svelte';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {type Snippet} from 'svelte';

    const composerContext = useComposerContext();

    interface Props {
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
</script>
<BorderBeam
    size="md"
    active={isActive}
    duration={3}
>
    {@render children()}
</BorderBeam>
