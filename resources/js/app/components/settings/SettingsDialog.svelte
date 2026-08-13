<!--
  @component Mock account settings surface. The dialog owns a hash router so
  each settings section has browser-history-aware navigation without leaving
  the current application page.
-->
<script lang="ts">
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import RouterView from '$lib/components/ui/routing/RouterView.svelte';
    import {createRouter} from '$lib/components/ui/routing/logistics/router.svelte.js';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import UserIcon from '$lib/components/ui/icons/iconset/UserIcon.svelte';
    import Notification02Icon from '$lib/components/ui/icons/iconset/Notification02Icon.svelte';
    import Shield01Icon from '$lib/components/ui/icons/iconset/Shield01Icon.svelte';
    import Settings05Icon from '$lib/components/ui/icons/iconset/Settings05Icon.svelte';
    import ProfileSettings from '$lib/app/components/settings/pages/ProfileSettings.svelte';
    import PreferenceSettings from '$lib/app/components/settings/pages/PreferenceSettings.svelte';
    import PrivacySettings from '$lib/app/components/settings/pages/PrivacySettings.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        open?: boolean;
        onOpenChange?: (open: boolean) => void;
    }

    let {open = $bindable(false), onOpenChange}: Props = $props();
    const {__} = useTranslator();

    const settingsRouter = createRouter('settings', (registrar) => {
        registrar
            .route('/', ProfileSettings)
            .route('/profile', ProfileSettings, {name: 'settings.profile'})
            .route('/preferences', PreferenceSettings, {name: 'settings.preferences'})
            .route('/privacy', PrivacySettings, {name: 'settings.privacy'});
    }, {strategy: 'hash'});

    const navItems: Array<{path: string; label: string; icon: IconComponent}> = [
        {path: '/profile', label: __('ui.profile.settingsNav.profile'), icon: UserIcon},
        {path: '/preferences', label: __('ui.profile.settingsNav.preferences'), icon: Notification02Icon},
        {path: '/privacy', label: __('ui.profile.settingsNav.privacy'), icon: Shield01Icon}
    ];

    $effect(() => {
        if (!open) return;

        const currentHashPath = window.location.hash.slice(1);
        const knownPath = navItems.some((item) => item.path === currentHashPath);
        if (!knownPath) {
            void settingsRouter.handle.goTo('/profile');
        }
    });

    function clearSettingsHash(): void {
        const path = window.location.hash.slice(1);
        if (path !== '/' && !navItems.some((item) => item.path === path)) return;

        window.history.replaceState(
            window.history.state,
            '',
            `${window.location.pathname}${window.location.search}`
        );
        window.dispatchEvent(new HashChangeEvent('hashchange'));
    }

    function handleOpenChange(isOpen: boolean): void {
        open = isOpen;
        if (!isOpen) clearSettingsHash();
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
        {__('ui.profile.settingsTitle')}
    {/snippet}
    {#snippet description()}
        {__('ui.profile.settingsDescription')}
    {/snippet}

    <div class="settings-layout">
        <nav class="settings-nav" aria-label={__('ui.profile.settingsNavigationLabel')}>
            {#each navItems as item (item.path)}
                {@const Icon = item.icon}
                <button
                    type="button"
                    class:active={settingsRouter.handle.isActive(item.path) || (item.path === '/profile' && settingsRouter.handle.path === '/')}
                    aria-current={settingsRouter.handle.isActive(item.path) ? 'page' : undefined}
                    onclick={() => settingsRouter.handle.goTo(item.path)}
                >
                    <Icon size={16}/>
                    <span>{item.label}</span>
                </button>
            {/each}
        </nav>

        <main class="settings-panel">
            <RouterView router={settingsRouter}/>
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
        gap: var(--space-1);
        padding: var(--space-4);
        border-right: var(--divider);
        background: color-mix(in oklch, var(--color-surface) 55%, transparent);
    }

    .settings-nav button {
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
        transition: background-color var(--duration-fast), color var(--duration-fast);
    }

    .settings-nav button:hover {
        background: var(--color-hover);
        color: var(--color-text);
    }

    .settings-nav button.active {
        background: var(--color-active-surface);
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
            flex-direction: row;
            overflow-x: auto;
            border-right: 0;
            border-bottom: var(--divider);
            padding: var(--space-2);
        }

        .settings-nav button {
            flex: 1 0 auto;
            justify-content: center;
        }

        .settings-panel {
            padding: var(--space-4);
        }
    }
</style>
