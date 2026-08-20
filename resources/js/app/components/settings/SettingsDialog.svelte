<!--
  @component Account settings surface. The dialog owns a hash router so each
  settings section has browser-history-aware navigation without leaving the
  current application page.
-->
<script lang="ts">
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import MenuList from '$lib/components/ui/menu-list/MenuList.svelte';
    import MenuListItem from '$lib/components/ui/menu-list/MenuListItem.svelte';
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

    interface Props {
        open?: boolean;
        onOpenChange?: (open: boolean) => void;
    }

    let {open = $bindable(false), onOpenChange}: Props = $props();
    const {__} = useTranslator();

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

    function handleOpenChange(isOpen: boolean): void {
        open = isOpen;
        onOpenChange?.(isOpen);
    }
</script>

<Dialog
    {open}
    onOpenChange={handleOpenChange}
    contentProps={{class: 'settings-dialog-content'}}
    headerProps={{class: 'settings-dialog-header'}}
>
    {#snippet title()}
        <Settings05Icon size={17}/>
        {__('ui.settings.title')}
    {/snippet}
    {#snippet description()}
        {__('ui.settings.description')}
    {/snippet}

    <div class="settings-layout">
        <nav class="settings-nav" aria-label={__('ui.settings.navLabel')}>
            <MenuList>
                {#each navItems as item (item.path)}
                    {@const Icon = item.icon}
                    {@const active = settingsRouter.handle.isActive(item.path) || (item.path === '/general' && settingsRouter.path === '/')}
                    <MenuListItem {active}>
                        {#snippet children({attach})}
                            <button
                                type="button"
                                {@attach attach}
                                class:active
                                aria-current={active ? 'page' : undefined}
                                onclick={() => settingsRouter.handle.goTo(item.path)}
                            >
                                <Icon size={16}/>
                                <span>{item.label}</span>
                            </button>
                        {/snippet}
                    </MenuListItem>
                {/each}
            </MenuList>
        </nav>

        <main class="settings-panel">
            <RouterView router={settingsRouter} loadingLabel={__('ui.loading')}/>
        </main>
    </div>
</Dialog>

<style>
    :global(.settings-dialog-content.settings-dialog-content) {
        width: min(46rem, calc(100vw - 2 * var(--space-4)));
        max-width: 46rem;
        height: min(36rem, calc(100dvh - 2 * var(--space-4)));
        grid-template-rows: auto minmax(0, 1fr);
        overflow: hidden;
        padding: 0;
        gap: 0;
    }

    :global(.settings-dialog-header.settings-dialog-header) {
        padding: var(--space-5) var(--space-6) var(--space-4);
        border-bottom: var(--divider);
    }

    .settings-layout {
        display: grid;
        min-height: 0;
        grid-template-columns: 11.5rem minmax(0, 1fr);
    }

    .settings-nav {
        display: flex;
        flex-direction: column;
        padding: var(--space-4);
        border-right: var(--divider);
        background: color-mix(in oklch, var(--color-surface) 55%, transparent);
    }

    .settings-nav button {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: var(--space-2);
        min-height: 2.25rem;
        padding: 0 var(--space-2_5);
        border: 0;
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-text-muted);
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
        overflow: auto;
        padding: var(--space-6);
    }

    @media (--bp-md-and-smaller) {
        :global(.settings-dialog-content.settings-dialog-content) {
            width: calc(100vw - 2 * var(--space-2));
            height: calc(100dvh - 2 * var(--space-2));
        }

        .settings-layout {
            grid-template-columns: 1fr;
            grid-template-rows: auto minmax(0, 1fr);
        }

        .settings-nav {
            border-right: 0;
            border-bottom: var(--divider);
            padding: var(--space-2);
        }

        .settings-panel {
            padding: var(--space-4);
        }
    }
</style>
