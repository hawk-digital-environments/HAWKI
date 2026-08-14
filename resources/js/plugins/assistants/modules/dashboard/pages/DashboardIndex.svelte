<script lang="ts">
    import {useStore} from "$lib/app/hooks/useStore.svelte";
    import {useApp} from "$lib/app/hooks/useApp.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";

    interface Props {
    }
    const {} = $props();

    // const testStore = useStore("test");
    const modelStore = useStore("ai-models");
    const {__} = useTranslator();
    const app = useApp();



</script>
<h1>Hello</h1>
