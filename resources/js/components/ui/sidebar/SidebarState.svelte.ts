import {createContext} from 'svelte';
import {
    type BreakpointState,
    useBreakpoint
} from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';

/**
 * Reactive source of truth for the sidebar layout's open/closed panels.
 * Created once by `SidebarRoot.svelte` and shared with every panel in the
 * subtree via {@link useSidebar}.
 */
class SidebarState {
    /** Whether the nav sidebar is open (expanded on desktop, on-canvas on mobile). */
    navOpen = $state(false);

    /** Whether the aside panel is open. */
    asideOpen = $state(false);

    public constructor(private readonly breakpoints: BreakpointState) {
        // Nav starts open on desktop but closed on mobile, where it is an
        // off-canvas overlay that would otherwise cover the content on first paint.
        this.navOpen = !this.mobile;
    }

    public get mobile(): boolean {
        return this.breakpoints.is('bpMdAndSmaller');
    }

    /** Flip the nav sidebar between open and closed. */
    toggleNav(): void {
        this.navOpen = !this.navOpen;
    }
}

const [getSidebarContext, setSidebarContext] = createContext<SidebarState>();

export function createSidebarContext(breakpoints: BreakpointState = useBreakpoint()): SidebarState {
    const sidebar = new SidebarState(breakpoints);
    setSidebarContext(sidebar);
    return sidebar;
}

export function useSidebar(): SidebarState {
    return getSidebarContext();
}
