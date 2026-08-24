<!--
  @component Grid root for the sidebar layout. Provides the shared sidebar
  state and owns the animated nav/main/aside tracks.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {createSidebarContext} from '$lib/components/ui/sidebar/SidebarState.svelte.js';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Sidebar layout content: navigation, main view and optional aside. */
        children: Snippet;
    }

    const {children, class: className, ...rest}: Props = $props();
    const sidebar = createSidebarContext();

    let resizing = $state(false);

    $effect(() => {
        let timer: ReturnType<typeof setTimeout>;
        const onResize = () => {
            resizing = true;
            clearTimeout(timer);
            timer = setTimeout(() => (resizing = false), 150);
        };

        window.addEventListener('resize', onResize);
        return () => {
            window.removeEventListener('resize', onResize);
            clearTimeout(timer);
        };
    });
</script>

<div
    {...mergeProps(rest, {
        class: [
            'sidebar-layout',
            sidebar.navOpen && 'nav-open',
            sidebar.asideOpen && 'aside-open',
            resizing && 'resizing',
            className
        ]
    })}
>
    {@render children()}
</div>

<style>
    /* --nav-track / --aside-track are registered in resources/css/properties.css. */

    :global(:root) {
        --nav-row-h: 2.25rem;
        --nav-group-gap: var(--space-3);
    }

    .sidebar-layout {
        position: relative;
        display: grid;
        width: 100%;
        height: 100vh;
        overflow: hidden;
        grid-template-columns: var(--nav-track) minmax(0, 1fr) var(--aside-track);
        grid-template-areas: 'nav main aside';
        transition:
            --nav-track 280ms var(--easing-spring),
            --aside-track 280ms var(--easing-spring);

        --nav-w: 16rem;
        --rail-w: calc(var(--nav-row-h) + 2 * var(--space-2) + var(--divider-width));
        --aside-w: 20rem;
        --nav-icon-size: 18px;
        --nav-item-pad-x: calc(
            (var(--rail-w) - 2 * var(--space-2) - var(--divider-width) - var(--nav-icon-size)) / 2
        );
        --nav-track: var(--nav-w);
        --aside-track: 0rem;
        --panel-bg: color-mix(in oklch, var(--color-surface-raised) 60%, transparent);

        background: var(--color-bg);
    }

    .sidebar-layout.resizing {
        transition: none;
    }

    .sidebar-layout.aside-open {
        --aside-track: var(--aside-w);
    }

    .sidebar-layout:not(.nav-open) {
        --nav-track: var(--rail-w);
    }

    @media (--bp-lg-and-smaller) {
        .sidebar-layout {
            --aside-w: 16rem;
        }
    }

    @media (--bp-md-and-smaller) {
        :global(:root) {
            --nav-row-h: 2.5rem;
        }

        .sidebar-layout {
            --nav-w: 13rem;
            grid-template-columns: minmax(0, 1fr);
            grid-template-areas: 'main';
        }
    }
</style>
