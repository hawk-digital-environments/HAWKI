<script lang="ts">
    import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte.js';
    import {onMount} from 'svelte';

    const {goToRoute, route, path, debug} = useRouter();

    interface Props {
    }

    const {}: Props = $props();

    onMount(() => {
        debug();
    });
</script>

<button onclick={() => goToRoute('chat.index', {id: '123'})}>Go to Chat</button>
