<!--
  Standalone detail route (`/assistants/dashboard/:id`): direct links and
  reloads. Shows the same detail modal the lists open (AssistantDetailModal),
  without a back button since the source is unknown; Escape lands in the store.
-->
<script lang="ts">
    import Page from '$lib/components/ui/page/Page.svelte';
    import DashboardHeader from '$plugins/assistants/components/breadcrumb/DashboardHeader.svelte';
    import AssistantDetailModal from '$plugins/assistants/modules/dashboard/components/assistantDetail/AssistantDetailModal.svelte';
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

    const goToStore = () => void router.goToRoute('assistants.dashboard.store');
</script>

<Page>
    {#snippet header()}
        <DashboardHeader />
    {/snippet}
    {#snippet body()}
        <AssistantDetailModal {id} onClose={goToStore} backButton={false} />
    {/snippet}
</Page>
