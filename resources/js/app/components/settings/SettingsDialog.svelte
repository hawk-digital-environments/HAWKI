<!--
  @component Account settings surface. The dialog owns a hash router so each
  settings section has browser-history-aware navigation without leaving the
  current application page. On wide screens it is a dialog with the sections
  in a sidebar beside the page; below `md` it is a bottom sheet with the
  sections as a segmented control above the page.
-->
<script module lang="ts">
    /** A settings section the dialog can be opened on. */
    export type {SettingsSection} from './types.js';
</script>

<script lang="ts">
    import type {Attachment} from 'svelte/attachments';
    import type {SettingsSection} from './types.js';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import BottomSheet from '$lib/components/ui/sheet/BottomSheet.svelte';
    import MenuList from '$lib/components/ui/menu-list/MenuList.svelte';
    import MenuListItem from '$lib/components/ui/menu-list/MenuListItem.svelte';
    import Tabs from '$lib/components/ui/tabs/Tabs.svelte';
    import RouterView from '$lib/components/ui/routing/RouterView.svelte';
    import {createRouter} from '$lib/components/ui/routing/index.js';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import UserIcon from '$lib/components/ui/icons/iconset/UserIcon.svelte';
    import FlaskConicalIcon from '$lib/components/ui/icons/iconset/FlaskConicalIcon.svelte';
    import Settings05Icon from '$lib/components/ui/icons/iconset/Settings05Icon.svelte';
    import GeneralSettings from '$lib/app/components/settings/pages/GeneralSettings.svelte';
    import ProfileSettings from '$lib/app/components/settings/pages/ProfileSettings.svelte';
    import ExperimentsSettings from '$lib/app/components/settings/pages/ExperimentsSettings.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useBreakpoint} from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';
    import {untrack} from 'svelte';

    interface Props {
        open?: boolean;
        onOpenChange?: (open: boolean) => void;
        /**
         * Section to show when the dialog opens (e.g. from the search
         * palette). Only read at the moment `open` flips to true; the user
         * can navigate freely afterwards. Defaults to the general page.
         */
        section?: SettingsSection | null;
    }

    let {open = $bindable(false), onOpenChange, section = null}: Props = $props();
    const uid = $props.id();
    const titleId = `${uid}-title`;
    const {__} = useTranslator();

    const breakpoint = useBreakpoint();
    const compact = $derived(breakpoint.is('bpSmallerThanMd'));

    const settingsRouter = createRouter('settings', (registrar) => {
        registrar
            .route('/', GeneralSettings)
            .route('/general', GeneralSettings, {name: 'settings.general'})
            .route('/profile', ProfileSettings, {name: 'settings.profile'})
            .route('/experiments', ExperimentsSettings, {name: 'settings.experiments'});
    }, {strategy: 'hash'});

    // $derived so the labels follow runtime locale switches from the general settings page.
    const navItems: Array<{path: string; label: string; icon: IconComponent}> = $derived([
        {path: '/general', label: __('ui.settings.nav.general'), icon: Settings05Icon},
        {path: '/profile', label: __('ui.settings.nav.profile'), icon: UserIcon},
        {path: '/experiments', label: __('ui.settings.nav.experiments'), icon: FlaskConicalIcon}
    ]);
    const tabItems = $derived(navItems.map(({path, label}) => ({key: path, label})));

    const activePath = $derived(
        navItems.find((item) => settingsRouter.handle.isActive(item.path))?.path
        ?? (settingsRouter.path === '/' ? '/general' : null)
    );

    function goTo(path: string): void {
        void settingsRouter.handle.goTo(path);
    }

    // Point the hash router at the requested section before the RouterView
    // mounts; the strategy writes the hash, and the view resolves from it.
    // `untrack` keeps the router's own state out of this effect's dependencies
    // so only `open`/`section` re-run it.
    $effect(() => {
        if (open && section) {
            untrack(() => void settingsRouter.handle.goTo(`/${section}`, {replace: true}));
        }
    });

    // The panel follows its content's measured height so a section switch
    // resizes the dialog (or sheet) smoothly; an `auto` height can't transition.
    let contentHeight = $state<number | null>(null);
    // While the height animates, the panel's own scrollbar would flash.
    let resizing = $state(false);

    const measureContent: Attachment<HTMLElement> = (element) => {
        const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
        const observer = new ResizeObserver(() => {
            const height = element.offsetHeight;
            // The first measurement lands instantly (auto → px doesn't animate).
            if (contentHeight !== null && height !== contentHeight && !reducedMotion.matches) {
                resizing = true;
            }
            contentHeight = height;
        });
        observer.observe(element);
        return () => {
            observer.disconnect();
            contentHeight = null;
            resizing = false;
        };
    };

    function endResize(event: TransitionEvent): void {
        if (event.target === event.currentTarget) resizing = false;
    }

    function handleOpenChange(isOpen: boolean): void {
        open = isOpen;
        onOpenChange?.(isOpen);
    }
</script>

{#snippet panel()}
    <div
        class="settings-panel"
        class:resizing
        aria-labelledby={titleId}
        style:--settings-content-height={contentHeight === null ? undefined : `${contentHeight}px`}
        ontransitionend={endResize}
        ontransitioncancel={endResize}
    >
        <div {@attach measureContent}>
            <RouterView router={settingsRouter} loadingLabel={__('ui.loading')}/>
        </div>
    </div>
{/snippet}

{#if compact}
    <BottomSheet {open} onOpenChange={handleOpenChange} title={__('ui.settings.title')}>
        <div class="settings-sheet">
            <Tabs items={tabItems} value={activePath} onChange={goTo} aria-label={__('ui.settings.navLabel')}/>
            {@render panel()}
        </div>
    </BottomSheet>
{:else}
    <Dialog
        {open}
        onOpenChange={handleOpenChange}
        contentProps={{class: 'settings-dialog-content'}}
        headerProps={{class: 'settings-dialog-header'}}
        titleProps={{id: titleId}}
    >
        {#snippet title()}
            <span class="settings-title">{__('ui.settings.title')}</span>
        {/snippet}

        <nav class="settings-nav" aria-label={__('ui.settings.navLabel')}>
            <MenuList>
                <ul class="settings-nav-list">
                    {#each navItems as item (item.path)}
                        {@const Icon = item.icon}
                        {@const active = activePath === item.path}
                        <li>
                            <MenuListItem {active}>
                                {#snippet children({attach})}
                                    <button
                                        type="button"
                                        {@attach attach}
                                        class:active
                                        aria-current={active ? 'page' : undefined}
                                        onclick={() => goTo(item.path)}
                                    >
                                        <Icon size={18} strokeWidth={2} aria-hidden="true"/>
                                        <span>{item.label}</span>
                                    </button>
                                {/snippet}
                            </MenuListItem>
                        </li>
                    {/each}
                </ul>
            </MenuList>
        </nav>

        {@render panel()}
    </Dialog>
{/if}

<style>
    /* One grid on a single surface: the nav on the left, the page scrolling on
       its own on the right, both below a header row holding the title and the
       dialog's close button (a 24px box inset by --space-4). Every edge — and
       the gap under the header — shares that one inset, and the corner radius
       is the frames' radius plus it, so the 8px fields inside sit concentric
       with it.

       The dialog is as tall as the current page and hangs from a fixed top
       edge, so switching to a shorter or taller section never moves the nav.
       The offset centres the tallest section (profile). */
    :global(.settings-dialog-content.settings-dialog-content) {
        --settings-top: max(var(--space-4), calc(50dvh - 15rem));

        top: var(--settings-top);
        translate: -50% 0;
        width: min(48rem, calc(100vw - 2 * var(--space-4)));
        max-width: none;
        max-height: calc(100dvh - var(--settings-top) - var(--space-4));
        grid-template-columns: 11rem minmax(0, 1fr);
        grid-template-rows: auto minmax(0, 1fr);
        overflow: hidden;
        padding: 0;
        gap: 0;
        border-radius: var(--corner-lg);
    }

    /* A 24px title line under the shared inset centres the title on the close
       button; on the left it lines up with the nav icons below it. */
    :global(.settings-dialog-header.settings-dialog-header) {
        grid-column: 1 / -1;
        padding: var(--space-4) var(--space-12) 0 calc(var(--space-4) + var(--space-2_5));
    }

    .settings-title {
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
    }

    .settings-nav {
        display: flex;
        flex-direction: column;
        min-height: 0;
        padding: var(--space-4) 0 var(--space-4) var(--space-4);
    }

    .settings-nav-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .settings-nav button {
        position: relative;
        /* Above the sliding highlight behind the nav rows. */
        --settings-nav-button-z: 1;
        z-index: var(--settings-nav-button-z);
        display: flex;
        align-items: center;
        gap: var(--space-2_5);
        width: 100%;
        min-height: 2.25rem;
        padding: 0 var(--space-2_5);
        border: 0;
        /* Same corners as the app sidebar rows (and MenuList's highlights). */
        border-radius: var(--corner-sm);
        background: transparent;
        /* Same resting ink as the app sidebar rows. */
        color: color-mix(in oklab, var(--color-text) 60%, var(--color-text-muted));
        font: inherit;
        font-size: var(--font-size-xs);
        text-align: left;
        cursor: pointer;
        transition: color var(--duration-fast);
    }

    .settings-nav button:hover {
        color: var(--color-text);
    }

    .settings-nav button.active {
        color: var(--color-active-text);
    }

    .settings-panel {
        min-width: 0;
        min-height: 0;
        max-height: 100%;
        overflow-y: auto;
        /* A little more air towards the nav than on the outer edges. */
        padding: var(--space-4) var(--space-4) var(--space-4) var(--space-6);
        /* Until the first measurement the property is unset, which makes this
           declaration invalid and leaves the height at auto. */
        height: calc(var(--settings-content-height) + 2 * var(--space-4));
        transition: height var(--duration-extra-fast) var(--easing-default);
    }

    .settings-panel.resizing {
        overflow-y: hidden;
    }

    @media (prefers-reduced-motion: reduce) {
        .settings-panel {
            transition: none;
        }
    }

    /* Sheet: segmented section nav above the page; the sheet body scrolls. */
    .settings-sheet {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    .settings-sheet .settings-panel {
        max-height: none;
        overflow: hidden;
        padding: 0;
        height: var(--settings-content-height);
    }
</style>
