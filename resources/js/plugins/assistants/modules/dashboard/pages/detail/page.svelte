<!--
  Standalone detail route (`/assistants/dashboard/:id`): direct links and
  reloads. From the lists, assistants open in the full-screen detail modal
  instead (see AssistantDetailModal), which keeps the list mounted beneath.
-->
<script lang="ts">
    import Page from '$lib/components/ui/page/Page.svelte';
    import PageHeaderBar from '$lib/components/ui/page/PageHeaderBar.svelte';
    import AssistantDetail from '$plugins/assistants/modules/dashboard/components/assistantDetail/AssistantDetail.svelte';
    import { useRouter } from '$lib/components/ui/routing/hooks/useRouter.svelte.js';
    import type { RouteParams } from '$lib/components/ui/routing/index.js';

    /**
     * The kernel's route renderer hands each matched page its route params
     * (see core's ChatConversation.svelte), hence the optional `params` prop.
     */
    interface Props {
        params?: RouteParams;
    }

    const { params = {} }: Props = $props();
    const router = useRouter();

    // The router types route params loosely (string | string[]) even though
    // `:id` routes always deliver a plain string.
    const id = $derived.by(() => {
        const rawId = params?.id;
        return (Array.isArray(rawId) ? rawId[0] : rawId) ?? '';
    });

    // Entered directly, there is no list to return to: back goes to the store.
    const goBack = () => void router.goToRoute('assistants.dashboard.store');

    // The bar shows the assistant's name once its detail resolves; until then
    // the row stays reserved (PageHeaderBar's spacer shape).
    let assistantName = $state<string | null>(null);

    // A different assistant: drop the stale name while its detail loads.
    $effect(() => {
        id;
        assistantName = null;
    });
</script>

<Page>
    {#snippet header()}
        {#if assistantName}
            <PageHeaderBar heading={assistantName} />
        {:else}
            <PageHeaderBar spacer />
        {/if}
    {/snippet}
    {#snippet body()}
        <AssistantDetail
            {id}
            onBack={goBack}
            underHeaderBar
            onLoaded={(assistant) => (assistantName = assistant.name)}
        />
    {/snippet}
</Page>
