import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import BubbleChatIcon from '$lib/components/ui/icons/iconset/BubbleChatIcon.svelte';
import ChatAddIcon from '$lib/components/ui/icons/iconset/ChatAddIcon.svelte';
import type {ChatSummary} from '$plugins/core/modules/chat/types.js';

const ACTIONS_GROUP_ID = 'core:chat.actions';
const GROUP_ID = 'core:chat.conversations';

/**
 * Contributes the chat module's entries to the search palette on the
 * kernel's `app.search`: a "Chat" group with the new-chat action, and the
 * "Conversations" group listing the user's chats. Called from
 * `CorePlugin.ready()`, once the `chat` store exists. The latter group reads
 * `chatStore.conversations` lazily (it is `$state`, and the palette evaluates
 * the getter in a `$derived`), so it stays current as chats are created,
 * renamed, or deleted, and
 * lists them newest first by the store's `updated_at` (which `ChatStore` bumps
 * on creation, activity, and rename — the same events that move a chat up on
 * the server). Ties keep the store's own order. The store's list is copied before sorting — rendering never
 * mutates it.
 */
export function registerChatSearch(app: HawkiApp): void {
    const chatStore = app.stores.get('chat');

    app.search.addGroup({
        id: ACTIONS_GROUP_ID,
        label: () => app.translator.translate('chat.module.title'),
        items: () => [{
            id: `${ACTIONS_GROUP_ID}/new`,
            title: app.translator.translate('chat.sidebar.newChat'),
            icon: ChatAddIcon,
            onSelect: () => {
                chatStore.startNew();
                void app.router.goToRoute('chat.index');
            }
        }]
    });

    app.search.addGroup({
        id: GROUP_ID,
        label: () => app.translator.translate('ui.search.conversations'),
        items: () => [...chatStore.conversations].sort(byNewestFirst).map(conversation => ({
            id: `${GROUP_ID}/${conversation.slug}`,
            title: conversation.name,
            icon: BubbleChatIcon,
            onSelect: () => void app.router.goToRoute('chat.conversation', {slug: conversation.slug})
        }))
    });
}

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
