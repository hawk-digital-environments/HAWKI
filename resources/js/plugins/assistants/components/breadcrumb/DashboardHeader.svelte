<!--
  @component Header bar for the assistants dashboard pages: the icon
  breadcrumb trail instead of a visible title. The trail starts at the
  sidebar's main menu items and derives from the same assistant menu entries
  the sidebar renders, so trail and sidebar can never drift apart. The page's
  full title stays in the document outline as PageHeaderBar's visually hidden
  h1 and doubles as the current crumb's tooltip. A lone crumb shows no trail.

  While an assistant's detail modal is open (`openDetail`, the list modal and
  the standalone route alike), the bar floats over its cover with only a back
  button that closes it.
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
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import type {AssistantMenuEntry} from '$plugins/assistants/hooks/assistantMenuHooks.svelte';
    import type {IconComponent} from '$lib/components/ui/icons';

    interface Props {
        /** The page's full title — the bar's visually hidden h1 and the
         *  current crumb's tooltip. */
        title?: string;
    }

    const {title}: Props = $props();

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

    /** The sidebar group the section sits in (My Assistants): its own crumb,
     *  with no page of its own, so it opens a menu of the group's sections. */
    const groupEntries = $derived(
        section?.group ? menu.entries.filter(entry => entry.level === 'dashboard' && entry.group === section.group) : []
    );

    const detail = $derived(openDetail.assistant);
    const closeDetail = $derived(detail ? openDetail.close : null);

    /** The open detail's cover background decides the back button's shade. */
    const coverMode = $derived.by(() => {
        if (!detail) return null;
        const avatar = resolveAssistantAvatar(detail.avatar, detail.name);
        return overlayContrastMode(cssDeclaration(avatar.iconCss, 'background'));
    });
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

<PageHeaderBar srHeading={detail?.name ?? title} overCover={detail !== null} data-cover-mode={coverMode ?? undefined}>
    <div class="lead" data-mode={coverMode ?? undefined}>
        {#if closeDetail}
            <ButtonWithTooltip
                variant="ghost"
                size="sm"
                iconLeft={ArrowLeft01Icon}
                tooltip={__('assistants.detail.back')}
                class="back"
                onclick={closeDetail}
            />
        {:else if section && groupEntries.length}
            <BreadcrumbTrail>
                {@render groupCrumb()}
                <BreadcrumbItem label={title ?? section.label} icon={section.icon} />
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
