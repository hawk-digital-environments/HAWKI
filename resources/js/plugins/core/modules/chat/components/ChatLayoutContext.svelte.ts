/**
 * Svelte context published by `ChatLayout.svelte`: the layout-owned DOM and
 * layout measurements the chat pages need for their scroll behaviour. The
 * pages render *inside* the layout's scroll region but own their scroll
 * effects (pin-to-bottom, new-turn alignment), so the layout hands them the
 * region element and its measured heights through this context. Heights also
 * reach page CSS through inherited custom properties set on `.chat-body`.
 */
import {createStableContext} from '$lib/utils/stableContext.js';

/** Reactive layout state owned by one `ChatLayout` mount. */
export class ChatLayoutState {
    /** The layout's scroll region — the pages' scroll effects act on it. */
    public scrollRegion = $state<HTMLDivElement | null>(null);
    /** Measured composer dock height in px (written by the dock's `height` binding). */
    public dockHeight = $state(0);
    /** Measured scroll region height in px (written by the `clientHeight` binding). */
    public regionHeight = $state(0);
}

const [get, set] = createStableContext<ChatLayoutState>('hawki.chat.layout-context');

/** Creates and publishes the layout state. Call once, during `ChatLayout` initialization. */
export function createChatLayoutContext(): ChatLayoutState {
    const state = new ChatLayoutState();
    set(state);
    return state;
}

/** Returns the `ChatLayoutState` published by the surrounding `ChatLayout`. */
export function useChatLayout(): ChatLayoutState {
    return get();
}
