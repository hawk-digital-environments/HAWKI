<!--
  Standalone detail route (`/assistants/dashboard/:id`): direct links and
  reloads. From the lists, assistants open in the full-screen detail modal
  instead (see AssistantDetailModal), which keeps the list mounted beneath.
-->
<script lang="ts">
    import Page from '$lib/components/ui/page/Page.svelte';
    import DashboardHeader from '$plugins/assistants/components/breadcrumb/DashboardHeader.svelte';
    import AssistantDetail from '$plugins/assistants/modules/dashboard/components/assistantDetail/AssistantDetail.svelte';
    import { useRouter } from '$lib/components/ui/routing/hooks/useRouter.svelte.js';
    import type { RouteParams } from '$lib/components/ui/routing/index.js';
    import type { Assistant } from '$plugins/assistants/types/assistant/Assistant';
    import { resolveAssistantAvatar } from '$plugins/assistants/utils/resolveAssistantAvatar';

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

    // Entered directly, there is no list to return to: no back button (the
    // trail's store crumb covers it), and a delete lands in the store.
    const goToStore = () => void router.goToRoute('assistants.dashboard.store');

    // The trail's leaf — and the bar's hidden h1: the loaded assistant.
    let assistant = $state<Assistant | null>(null);

    // A different assistant: drop the stale one while its detail loads.
    $effect(() => {
        id;
        assistant = null;
    });
</script>

<Page>
    {#snippet header()}
        <DashboardHeader
            title={assistant?.name}
            leaf={assistant
                ? {
                    label: assistant.name,
                    avatar: resolveAssistantAvatar(assistant.avatar, assistant.name)
                }
                : null}
        />
    {/snippet}
    {#snippet body()}
        <AssistantDetail
            {id}
            onDeleted={goToStore}
            underHeaderBar
            onLoaded={(loaded) => (assistant = loaded)}
        />
    {/snippet}
</Page>
