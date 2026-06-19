<script lang="ts">

    import BorderBeam from '$lib/components/ui/border-beam/BorderBeam.svelte';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {type Snippet} from 'svelte';

    const composerContext = useComposerContext();

    interface Props {
        children: Snippet;
    }

    const {children}: Props = $props();
</script>
<BorderBeam
    size="md"
    active={(composerContext.sendStatus?.active ?? false) || composerContext.forcedActive}
    duration={3}
>
    {@render children()}
</BorderBeam>
