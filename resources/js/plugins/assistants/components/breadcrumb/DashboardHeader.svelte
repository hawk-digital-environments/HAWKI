<!--
  @component Header bar for the assistants dashboard pages: the icon
  breadcrumb trail instead of a visible title. The trail starts at the
  sidebar's main menu items and derives from the same assistant menu entries
  the sidebar renders, so trail and sidebar can never drift apart. The page's full title stays in the
  document outline as PageHeaderBar's visually hidden h1 and doubles as the
  current crumb's tooltip.

  Without a `leaf`, the active section ends the trail as its current crumb
  (the list pages). With a leaf — the detail route's assistant, or the one
  open in a list's detail modal (`openDetail`) — the section stays navigable
  (falling back to the store, the detail's back target; opted out of current
  while the leaf is missing, since the trail then changes shape in place) and
  the leaf becomes the current crumb, shown as the assistant's avatar disc.

  The list modal's leaf also gets a back button before the trail: it closes
  the overlay back onto the list. The standalone route has no list to return
  to, so it shows none.
-->
<script lang="ts">
    import PageHeaderBar from '$lib/components/ui/page/PageHeaderBar.svelte';
    import {BreadcrumbTrail, BreadcrumbItem} from '$plugins/assistants/components/breadcrumb';
    import {useAssistantMenuEntries} from '$plugins/assistants/hooks/assistantMenuHooks.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';
    import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte';
    import type {AssistantAvatar} from '$plugins/assistants/types/assistant/AssistantAvatar';
    import {openDetail} from '$plugins/assistants/components/breadcrumb/openDetail.svelte';
    import {resolveAssistantAvatar} from '$plugins/assistants/utils/resolveAssistantAvatar';
    import {cssDeclaration, overlayContrastMode} from '$lib/utils/color.js';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import UserAiIcon from '$lib/components/ui/icons/iconset/UserAiIcon.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import type {AssistantMenuEntry} from '$plugins/assistants/hooks/assistantMenuHooks.svelte';
    import type {IconComponent} from '$lib/components/ui/icons';

    interface Props {
        /** The page's full title — the bar's visually hidden h1 and the
         *  current crumb's tooltip. */
        title?: string;
        /** Deepest crumb for a page below the sections (the detail route's
         *  assistant). */
        leaf?: {label: string; avatar?: AssistantAvatar} | null;
    }

    const {title, leaf = null}: Props = $props();

    const router = useRouter();
    const {__} = useTranslator();
    const menu = useAssistantMenuEntries();

    // The sidebar's active dashboard section (same precedence as its rows).
    const section = $derived(
        menu.entries.find(entry =>
            entry.level === 'dashboard'
            && (entry.active ?? (entry.route ? router.isRouteActive(entry.route) : false))
        ) ?? null
    );

    /** Without an active section (the detail route): the store, the back target. */
    const parent = $derived(section ?? menu.entries.find(entry => entry.id === 'dashboard.store') ?? null);

    /** The trail's leaf: the explicit one (the detail route), else the
     *  assistant open in a list's detail modal (its breadcrumb counterpart). */
    const leafCrumb = $derived(
        leaf
        ?? (openDetail.assistant ? {
            label: openDetail.assistant.name,
            avatar: resolveAssistantAvatar(openDetail.assistant.avatar, openDetail.assistant.name)
        } : null)
    );

    /** The leaf's cover background decides the chips' shade — but only where
     *  the bar actually floats over the cover (the list modal's leaf, not
     *  the standalone route's, whose bar sits on the page background). */
    const coverMode = $derived(
        !leaf && leafCrumb?.avatar ? overlayContrastMode(cssDeclaration(leafCrumb.avatar.iconCss, 'background')) : null
    );

    /** The sidebar group the section sits in (My Assistants): its own crumb,
     *  with no page of its own, so it opens a menu of the group's sections. */
    const groupEntries = $derived(
        parent?.group ? menu.entries.filter(entry => entry.level === 'dashboard' && entry.group === parent.group) : []
    );

    /** A lone crumb says nothing the sidebar doesn't: the trail shows from two up. */
    const crumbCount = $derived(
        (groupEntries.length ? 1 : 0) + (leafCrumb ? (parent ? 2 : 1) : section ? 1 : 0)
    );

    const closeModal = $derived(!leaf && openDetail.assistant ? openDetail.close : null);
</script>

{#snippet groupCrumb()}
    {#if groupEntries.length}
        <BreadcrumbItem label={__('assistants.sidebar.my_assistants')} icon={UserAiIcon} current={false}>
            {#snippet menu()}
                {#each groupEntries as entry (entry.id)}
                    {@render groupItem(entry)}
                {/each}
            {/snippet}
        </BreadcrumbItem>
    {/if}
{/snippet}

{#snippet groupItem(entry: AssistantMenuEntry)}
    <DropdownMenuItem
        iconLeft={entry.icon as IconComponent}
        disabled={entry.disabled}
        onSelect={() => (entry.route ? void router.goToRoute(entry.route) : undefined)}
    >{entry.label}</DropdownMenuItem>
{/snippet}

<PageHeaderBar srHeading={leafCrumb?.label ?? title} overCover={leafCrumb !== null} data-cover-mode={coverMode ?? undefined}>
    <div class="lead" data-mode={coverMode ?? undefined}>
        {#if closeModal}
            <ButtonWithTooltip
                variant="ghost"
                size="sm"
                iconLeft={ArrowLeft01Icon}
                tooltip={__('assistants.detail.back')}
                class="back"
                onclick={closeModal}
            />
        {/if}
        {#if crumbCount > 1}
            <BreadcrumbTrail mode={coverMode}>
                {#if leafCrumb}
                    {@render groupCrumb()}
                    {#if parent}
                        <BreadcrumbItem label={parent.label} icon={parent.icon} route={parent.route} current={false} />
                    {/if}
                    <BreadcrumbItem label={leafCrumb.label} avatar={leafCrumb.avatar} current />
                {:else if section}
                    {@render groupCrumb()}
                    <BreadcrumbItem label={title ?? section.label} icon={section.icon} />
                {/if}
            </BreadcrumbTrail>
        {/if}
    </div>
</PageHeaderBar>

<style>
    /* The trail's own gap to the nav trigger moves here, before the back button. */
    .lead {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        margin-left: var(--space-2);
    }
    .lead :global(.breadcrumb-trail) {
        margin-left: 0;
    }

    /* A round chip in the cover's shade, matching the trail's pill. */
    .lead[data-mode='dark'] {
        --back-fg: oklch(100% 0 0);
    }
    .lead[data-mode='light'] {
        --back-fg: oklch(24% 0 0);
    }
    .lead :global(.btn.back) {
        --btn-bg: color-mix(in oklch, var(--back-fg, currentColor) 14%, transparent);
        --btn-color: var(--back-fg, currentColor);
        width: var(--nav-row-h);
        height: var(--nav-row-h);
        padding: 0;
        border-radius: var(--corner-full);
        pointer-events: auto;
    }
    .lead :global(.btn.back:not(:disabled):hover) {
        --btn-bg: color-mix(in oklch, var(--back-fg, currentColor) 24%, transparent);
    }
</style>
