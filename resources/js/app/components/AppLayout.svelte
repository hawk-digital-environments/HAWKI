<!--
  @component App-level layout assembly: the grid shell with the navigation
  sidebar in the `nav` column and the page content in the `main` column. The
  layout mechanics (open/closed state, rail collapse, off-canvas behaviour)
  live in the components under components/ui/sidebar.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import AppSidebar from '$lib/app/components/sidebar/AppSidebar.svelte';
    import SidebarContent from '$lib/components/ui/sidebar/SidebarContent.svelte';
    import SidebarRoot from '$lib/components/ui/sidebar/SidebarRoot.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Toaster from '$lib/components/ui/toast/Toaster.svelte';
    import AnnouncementDialog from '$lib/app/components/announcements/AnnouncementDialog.svelte';

    interface Props {
        /** Page content, rendered in the layout's main column. */
        children: Snippet;
        meta?: {chrome?: string};
    }

    const {children, meta}: Props = $props();
    const app = useApp();
    const {__} = useTranslator();
</script>

{#if app.logoutState !== 'idle'}
    <main id="main-content" tabindex="-1" class="logout-status">
        <h1>{__('ui.profile.logout')}</h1>
        {#if app.logoutState === 'failed'}
            <p role="alert">{__('session.logoutFailed')}</p>
            <Button onclick={() => { void app.logout().catch(() => {}); }}>{__('session.retryLogout')}</Button>
        {:else}
            <p role="status">{__('session.loggingOut')}</p>
        {/if}
    </main>
{:else if meta?.chrome === 'none' || !app.cryptoReady}
    {@render children()}
    <Toaster />
{:else}
<SidebarRoot>
    <a class="skip-link" href="#main-content">{__('ui.navigation.skipToContent')}</a>
    <AppSidebar />
    <SidebarContent>
        {@render children()}
    </SidebarContent>

    <Toaster />
    <AnnouncementDialog />
</SidebarRoot>
{/if}

<style>
    .logout-status {
        max-width: 32rem;
        margin: 15vh auto;
        padding: var(--space-6);
    }

    .skip-link {
        position: fixed;
        top: var(--space-2);
        left: var(--space-2);
        /* Above the off-canvas nav drawer (--app-sidebar-z: 20), which it has
           to stay reachable over, but below --layer-overlay so a focused skip
           link can never paint on top of an open dialog. */
        --skip-link-z: 30;
        z-index: var(--skip-link-z);
        padding: var(--space-2) var(--space-3);
        border-radius: var(--corner-sm);
        background: var(--color-interactive);
        color: var(--color-on-interactive);
        font-weight: var(--font-weight-semibold);
        transform: translateY(calc(-100% - var(--space-4)));
        transition: transform var(--duration-fast);
    }

    .skip-link:focus {
        transform: translateY(0);
    }

    /* While the mobile drawer is open the main landmark is `inert`, so a skip
       link pointing at it would be a focusable dead end: hide it along with
       the content. `SidebarRoot` flags the open drawer on the grid root. */
    @media (--bp-md-and-smaller) {
        :global(.sidebar-layout.nav-open) .skip-link {
            display: none;
        }
    }
</style>
