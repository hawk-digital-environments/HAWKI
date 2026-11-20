<!--
  @component The builder's sidebar action: the "Zurück" button, contributed
  to the app sidebar's pinned action area via the `sidebarSlots` hook (see
  `AssistantsPlugin.hooks()`); its slot is active on builder routes only,
  taking the place of `CreateAssistantButton` while the builder is open.

  Leaves the builder for the page it was opened from — the assistant's detail
  page, the drafts list, wherever the user hit "Edit", "Remix" or "Erstellen".
  Entering the builder without an origin (a direct URL, say) falls back to the
  drafts list, where a freshly created assistant now lives. The exit
  confirmation runs on top of this as a router navigation guard (see
  `ConfirmBuilderExit`), which can still cancel the navigation.
-->
<script lang="ts">
    import SidebarButton from '$lib/components/ui/sidebar/SidebarButton.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {builderReturnPath} from '$plugins/assistants/modules/builder/contexts/builderReturn.js';

    const router = useRouter();
    const sidebar = useSidebar();
    const {__} = useTranslator();

    function back() {
        if (sidebar.mobile) sidebar.navOpen = false;
        router.goTo(builderReturnPath() ?? router.getPath('assistants.dashboard.drafts'));
    }
</script>

<SidebarButton
    icon={ArrowLeft01Icon}
    label={__('assistants.sidebar.back')}
    variant="stroke"
    onclick={back}
/>
