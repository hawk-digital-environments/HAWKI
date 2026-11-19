<!--
  @component Header bar for the assistants dashboard pages: the icon
  breadcrumb trail instead of a visible title. The crumbs derive themselves
  from the same sources the sidebar renders — the module's own icon/title
  (what SpaceTabs shows) and the active section from the assistant menu — so
  trail and sidebar can never drift apart. The page's full title stays in the
  document outline as PageHeaderBar's visually hidden h1 and doubles as the
  current crumb's tooltip.

  Without a `leaf`, the active section ends the trail as its current crumb
  (the list pages). With a leaf — the detail route's assistant — the section
  stays navigable (falling back to the store, the detail's back target;
  opted out of current while the leaf is missing, since the trail then
  changes shape in place) and the leaf becomes the current crumb, shown as
  the assistant's avatar disc.
-->
<script lang="ts">
    import PageHeaderBar from '$lib/components/ui/page/PageHeaderBar.svelte';
    import {BreadcrumbTrail, BreadcrumbItem} from '$plugins/assistants/components/breadcrumb';
    import {useAssistantMenuEntries} from '$plugins/assistants/hooks/assistantMenuHooks.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';
    import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte';
    import type {AssistantAvatar} from '$plugins/assistants/types/assistant/AssistantAvatar';

    interface Props {
        /** The page's full title — the bar's visually hidden h1 and the
         *  current crumb's tooltip. */
        title?: string;
        /** Deepest crumb for a page below the sections (the detail route's
         *  assistant). */
        leaf?: {label: string; avatar?: AssistantAvatar} | null;
    }

    const {title, leaf = null}: Props = $props();

    const app = useApp();
    const router = useRouter();
    const {__, translate} = useTranslator();
    const menu = useAssistantMenuEntries();

    // The module's identity in the space tabs — one source of truth for its
    // icon and label.
    const locale = $derived(app.localization.locale);
    const module = $derived(
        app.modules.all.find(m => m.plugin.name === 'assistants' && m.name === 'dashboard')
    );
    const moduleIcon = $derived.by(() => {
        const icon = module?.icon?.(locale);
        return typeof icon === 'string' ? undefined : icon;
    });
    const moduleLabel = $derived(module?.title?.(translate, locale) ?? __('assistants.assistants'));

    // The sidebar's active dashboard section (same precedence as its rows).
    const section = $derived(
        menu.entries.find(entry =>
            entry.level === 'dashboard'
            && (entry.active ?? (entry.route ? router.isRouteActive(entry.route) : false))
        ) ?? null
    );

    /** Without an active section (the detail route): the store, the back target. */
    const parent = $derived(section ?? menu.entries.find(entry => entry.id === 'dashboard.store') ?? null);
</script>

<PageHeaderBar srHeading={title}>
    <BreadcrumbTrail>
        {#if leaf}
            <BreadcrumbItem label={moduleLabel} icon={moduleIcon} route="assistants.dashboard.index" />
            {#if parent}
                <BreadcrumbItem label={parent.label} icon={parent.icon} route={parent.route} current={false} />
            {/if}
            <BreadcrumbItem label={leaf.label} avatar={leaf.avatar} current />
        {:else if section}
            <BreadcrumbItem label={moduleLabel} icon={moduleIcon} route="assistants.dashboard.index" />
            <BreadcrumbItem label={title ?? section.label} icon={section.icon} />
        {:else}
            <!-- No section matched and no leaf yet: the module crumb is where
                the trail ends. -->
            <BreadcrumbItem label={moduleLabel} icon={moduleIcon} />
        {/if}
    </BreadcrumbTrail>
</PageHeaderBar>
