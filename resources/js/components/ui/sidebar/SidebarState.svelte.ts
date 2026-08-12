import { createContext } from 'svelte';
import { MediaQuery } from 'svelte/reactivity';

/**
 * Viewport at or below which the layout switches to its mobile arrangement
 * (off-canvas nav). Mirrors the `--bp-mode-mobile` custom-media token used
 * by the CSS.
 */
const MOBILE_QUERY = '(max-width: 850px)';

/**
 * Reactive source of truth for the sidebar layout's open/closed panels.
 * Created once by `SidebarContext.svelte` and shared with every panel in the
 * subtree via {@link useSidebar}.
 */
export class SidebarState {
    /** Tracks the mobile breakpoint so defaults and behaviour can branch on it. */
    readonly mobile = new MediaQuery(MOBILE_QUERY);

    /** Whether the nav sidebar is open (expanded on desktop, on-canvas on mobile). */
    navOpen = $state(false);

    /** Whether the aside panel is open. */
    asideOpen = $state(false);

    constructor() {
        // Nav starts open on desktop but closed on mobile, where it is an
        // off-canvas overlay that would otherwise cover the content on first paint.
        this.navOpen = !this.mobile.current;
    }

    /** Flip the nav sidebar between open and closed. */
    toggleNav(): void {
        this.navOpen = !this.navOpen;
    }
}

export const [getSidebarContext, setSidebarContext] = createContext<SidebarState>();

export const useSidebar = () => getSidebarContext();
