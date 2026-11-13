<!--
  @component The review detail's sidebar action: the "Back" button to the
  queue overview, contributed via the `sidebarSlots` hook (see
  `AssistantsPlugin.hooks()`). The slot is active on the whole admin section;
  the button hides itself while the queue index is shown — there is nothing
  to go back to there.
-->
<script lang="ts">
    import SidebarButton from '$lib/components/ui/sidebar/SidebarButton.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';

    const router = useRouter();
    const sidebar = useSidebar();
    const {__} = useTranslator();

    const onIndex = $derived(router.isRouteActive('admin.assistants.index'));

    function back() {
        if (sidebar.mobile) sidebar.navOpen = false;
        router.goToRoute('admin.assistants.index');
    }
</script>

{#if !onIndex}
    <SidebarButton
        icon={ArrowLeft01Icon}
        label={__('assistants.sidebar.back')}
        variant="stroke"
        onclick={back}
    />
{/if}
