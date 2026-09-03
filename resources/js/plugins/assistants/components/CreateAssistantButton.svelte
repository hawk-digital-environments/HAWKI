<!--
  @component The assistants module's primary sidebar action: the "Erstellen"
  button, contributed to the app sidebar's pinned action area via the
  `sidebarSlots` hook (see `AssistantsPlugin.hooks()`); its slot is active on
  dashboard routes only, so it hides while the builder is open — mirroring the
  drill level of `AssistantsSidebar`.

  Drills into a fresh builder session: stashes a create intent (the builder
  layout picks it up and mints a new assistant — an explicit create, so any
  restored session draft is discarded) and navigates to the builder's first
  section.
-->
<script lang="ts">
    import SidebarButton from '$lib/components/ui/sidebar/SidebarButton.svelte';
    import AddCircleIcon from '$lib/components/ui/icons/iconset/AddCircleIcon.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {requestBuilderIntent} from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';

    const router = useRouter();
    const sidebar = useSidebar();
    const {__} = useTranslator();

    function startCreate() {
        if (sidebar.mobile) sidebar.navOpen = false;
        requestBuilderIntent({type: 'create'});
        router.goToRoute('assistants.builder.general');
    }
</script>

<SidebarButton
    icon={AddCircleIcon}
    label={__('assistants.sidebar.create')}
    onclick={startCreate}
/>
