<!--
  @component Header bar for the assistants dashboard pages: the icon
  breadcrumb trail instead of a visible title. The trail starts at the
  sidebar's main menu items and derives from the same assistant menu entries
  the sidebar renders, so trail and sidebar can never drift apart. The page's
  full title stays in the document outline as PageHeaderBar's visually hidden
  h1 and doubles as the current crumb's tooltip. The trail shows only with two
  or more crumbs, and then only when the sidebar is collapsed or the trail runs
  deeper than two levels.

  While an assistant's detail modal is open (`openDetail`, the list modal and
  the standalone route alike), the bar floats over its cover with only a back
  button that closes it, shown only when its source is known.
-->
<script lang="ts">
    import PageHeaderBar from '$lib/components/ui/page/PageHeaderBar.svelte';
    import {BreadcrumbTrail, BreadcrumbItem} from '$plugins/assistants/components/breadcrumb';
    import {useAssistantMenuEntries} from '$plugins/assistants/hooks/assistantMenuHooks.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';
    import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte';
    import {openDetail} from '$plugins/assistants/components/breadcrumb/openDetail.svelte';
    import {resolveAssistantAvatar} from '$plugins/assistants/utils/resolveAssistantAvatar';
    import {cssDeclaration, overlayContrastMode} from '$lib/utils/color.js';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import UserAiIcon from '$lib/components/ui/icons/iconset/UserAiIcon.svelte';
    import DropdownMenuRadioGroup from '$lib/components/ui/dropdown-menu/DropdownMenuRadioGroup.svelte';
    import DropdownMenuRadioItem from '$lib/components/ui/dropdown-menu/DropdownMenuRadioItem.svelte';
    import type {IconComponent} from '$lib/components/ui/icons';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';

    interface Props {
        /** The page's full title — the bar's visually hidden h1 and the
         *  current crumb's tooltip. */
        title?: string;
    }

    const {title}: Props = $props();

    const router = useRouter();
    const {__} = useTranslator();
    const menu = useAssistantMenuEntries();
    const sidebar = useSidebar();

    // The sidebar's active dashboard section (same precedence as its rows).
    const section = $derived(
        menu.entries.find(entry =>
            entry.level === 'dashboard'
            && (entry.active ?? (entry.route ? router.isRouteActive(entry.route) : false))
        ) ?? null
    );

    /** The sidebar group the section sits in (My Assistants): a plain label
     *  crumb, while the section crumb switches between the group's sections. */
    const groupEntries = $derived(
        section?.group ? menu.entries.filter(entry => entry.level === 'dashboard' && entry.group === section.group) : []
    );

    /** An open sidebar already shows where you are: the trail only earns its
     *  place with the sidebar collapsed, or deeper than two levels. */
    const depth = $derived(section ? (groupEntries.length ? 2 : 1) : 0);
    const showTrail = $derived(depth > 1 && (!sidebar.navOpen || depth > 2));

    function openSection(id: string): void {
        const route = groupEntries.find(entry => entry.id === id)?.route;
        if (route) void router.goToRoute(route);
    }

    const detail = $derived(openDetail.assistant);
    const closeDetail = $derived(detail ? openDetail.close : null);

    const coverCss = $derived(detail ? resolveAssistantAvatar(detail.avatar, detail.name).iconCss : null);
    /** The cover background's lightness keys the bar; the back button takes the cover's own foreground shade. */
    const coverMode = $derived(coverCss ? overlayContrastMode(cssDeclaration(coverCss, 'background')) : null);
    const coverForeground = $derived(coverCss ? cssDeclaration(coverCss, 'color') : null);

    $effect(() => {
        sidebar.triggerTint = coverForeground;
        return () => {
            sidebar.triggerTint = null;
        };
    });
</script>
{#snippet sectionMenu()}
    <DropdownMenuRadioGroup value={section?.id} onValueChange={openSection}>
        {#each groupEntries as entry (entry.id)}
            <DropdownMenuRadioItem
                value={entry.id}
                indicator="check"
                indicatorSide="end"
                iconLeft={entry.icon as IconComponent}
                disabled={entry.disabled}
            >{entry.label}</DropdownMenuRadioItem>
        {/each}
    </DropdownMenuRadioGroup>
{/snippet}

<PageHeaderBar srHeading={detail?.name ?? title} overCover={detail !== null} data-cover-mode={coverMode ?? undefined}>
    <div class="lead" data-mode={coverMode ?? undefined} style:--back-fg={coverForeground ?? undefined}>
        {#if detail}
            {#if closeDetail}
                <ButtonWithTooltip
                    variant="ghost"
                    size="sm"
                    iconLeft={ArrowLeft01Icon}
                    tooltip={__('assistants.detail.back')}
                    class="back"
                    onclick={closeDetail}
                />
            {/if}
        {:else if section && showTrail}
            <BreadcrumbTrail>
                <BreadcrumbItem label={__('assistants.sidebar.my_assistants')} icon={UserAiIcon} current={false} />
                <BreadcrumbItem label={title ?? section.label} icon={section.icon} current menu={sectionMenu} />
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
    /* The back button sits the cover actions' own gap from the nav trigger. */
    .lead:has(:global(.btn.back)) {
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
        pointer-events: auto;
    }
    .lead :global(.btn.back:not(:disabled):hover) {
        --btn-bg: color-mix(in oklch, var(--back-fg, currentColor) 24%, transparent);
    }
</style>
