<!--
  @component App-level light-dismiss for the mobile navigation overlay.
  Renders no markup: while the nav is open on mobile (where it is an
  off-canvas overlay, see Sidebar), a pointer press on the page outside it
  closes the nav.
-->
<script lang="ts">
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';

    const sidebar = useSidebar();

    // Ensure an activation delay: the very press that opened the overlay
    // must never immediately dismiss it.
    let activated = false;

    $effect(() => {
        if (!sidebar.mobile || !sidebar.navOpen) return;

        activated = false;
        const timer = setTimeout(() => (activated = true), 0);
        return () => clearTimeout(timer);
    });

    function handlePointerDown(event: PointerEvent): void {
        if (!activated) return;
        // A press on the document's scrollbars reports coordinates beyond
        // the viewport's inner size; not a press "on the page".
        if (
            event.clientX > document.documentElement.clientWidth
            || event.clientY > document.documentElement.clientHeight
        ) {
            return;
        }
        const path = event.composedPath().filter((el): el is HTMLElement => el instanceof HTMLElement);
        // The mobile trigger's own click toggles the nav — acting on its
        // pointerdown too would close here and re-open there.
        if (path.some((el) => el.id === 'mobile-navigation-trigger')) {
            return;
        }
        // Presses inside the nav itself belong to its rows and header.
        if (path.some((el) => el.id === 'app-navigation')) {
            return;
        }
        // Open floating layers (module palette, profile sheet) portal
        // outside the nav's DOM, but a press there belongs to the layer —
        // switching modules or picking a menu action keeps the nav open.
        // Every bits-ui layer marks its content with data-state="open".
        if (path.some((el) => el.dataset.state === 'open')) {
            return;
        }
        sidebar.navOpen = false;
    }
</script>

<svelte:document
    onpointerdown={sidebar.mobile && sidebar.navOpen ? handlePointerDown : undefined}
/>
