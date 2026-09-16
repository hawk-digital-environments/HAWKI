import BubbleChatIcon from '$lib/components/ui/icons/iconset/BubbleChatIcon.svelte';
import ChatAddIcon from '$lib/components/ui/icons/iconset/ChatAddIcon.svelte';
import type {StaticSource} from '$lib/kernel/search/types.js';
import type {ChatSummary} from '$plugins/core/modules/chat/types.js';

/** Existing Chat actions and loaded titles, observed and indexed by the kernel. */

/** The new-chat action. Always available. */
export const chatActionSource: StaticSource = {
    items: ({app}) => [{
        id: 'new',
        entityKey: 'action/core:chat/new',
        title: app.translator.translate('chat.sidebar.newChat'),
        icon: ChatAddIcon,
        onSelect: () => {
            app.stores.get('chat').startNew();
            void app.router.goToRoute('chat.index');
        }
    }]
};

/**
 * The user's conversations, newest first by the store's `updated_at` (which
 * `ChatStore` bumps on creation, activity, and rename — the same events that
 * move a chat up on the server). Ties keep the store's own order. The store's
 * list is copied before sorting — searching never mutates it.
 *
 * `ChatStore` fetches *every* page of summaries on load, so all titles are
 * held locally and no server-side counterpart is needed.
 */
export const chatConversationSource: StaticSource = {
    items: ({app}) => [...app.stores.get('chat').conversations].sort(byNewestFirst).map(conversation => ({
        id: conversation.slug,
        entityKey: `ai-convs/${conversation.slug}`,
        title: conversation.name,
        icon: BubbleChatIcon,
        onSelect: () => void app.router.goToRoute('chat.conversation', {slug: conversation.slug})
    }))
};

/** Sort comparator: later `updated_at` first; rows without one go last. */
function byNewestFirst(a: ChatSummary, b: ChatSummary): number {
    const left = timestamp(a.updated_at);
    const right = timestamp(b.updated_at);
    if (left === right) return 0;
    return right > left ? 1 : -1;
}

/** Parsed `updated_at`, or `-Infinity` when missing or unparseable. */
function timestamp(value: string | null): number {
    const time = value ? Date.parse(value) : Number.NaN;
    return Number.isNaN(time) ? Number.NEGATIVE_INFINITY : time;
}
