<script lang="ts">
    import Link from '$lib/components/util/link/Link.svelte';

    /**
     * The kernel's route renderer instantiates page components without passing
     * any props, so this interface is intentionally empty. Add route-derived
     * props here (and document them individually) once the real chat view lands.
     */
    interface Props {
        params: { id: string };
    }

    const {params}: Props = $props();

</script>
<h1>Single chat!</h1>
<p>This is a single conversation with {params.id}</p>
<Link href="@chat.index">Go to chat index</Link>
<hr/>
<Link href="/new">Go back to main page</Link>
