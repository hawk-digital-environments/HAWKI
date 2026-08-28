import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import BubbleChatIcon from '$lib/components/ui/icons/iconset/BubbleChatIcon.svelte';
import ChatAddIcon from '$lib/components/ui/icons/iconset/ChatAddIcon.svelte';
import MessageSearch01Icon from '$lib/components/ui/icons/iconset/MessageSearch01Icon.svelte';
import type {ChatSummary} from '$plugins/core/modules/chat/types.js';

const ACTIONS_GROUP_ID = 'core:chat.actions';
const GROUP_ID = 'core:chat.conversations';
const MESSAGES_GROUP_ID = 'core:chat.messages';

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
 *
 * A third "Messages" group lists the messages in the `chat-index` store (the
 * "Chat Index" experiment), one row per message: the start of the text as
 * the title, the full text as keywords so any part of it is findable, and
 * "in <conversation>" below so the user sees which chat a hit belongs to. Like the others it is a plain getter over
 * `$state`, so the palette indexes it and stays current as the index grows.
 * It is empty while the experiment is off.
 */
export function registerChatSearch(app: HawkiApp): void {
    const chatStore = app.stores.get('chat');
    const chatIndex = app.stores.get('chat-index');

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

    app.search.addGroup({
        id: MESSAGES_GROUP_ID,
        label: () => app.translator.translate('ui.search.messages'),
        items: () => chatIndex.messages.map(entry => ({
            id: `${MESSAGES_GROUP_ID}/${entry.slug}/${entry.message.id}`,
            title: excerpt(entry.message.text),
            description: app.translator.translate('ui.search.inConversation', {name: entry.name}),
            keywords: [entry.message.text],
            icon: MessageSearch01Icon,
            onSelect: () => void app.router.goToRoute('chat.conversation', {slug: entry.slug})
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

/** First line-ish of a message, collapsed to one line and cut for the palette row. */
function excerpt(text: string, max = 120): string {
    const flat = text.replace(/\s+/g, ' ').trim();
    return flat.length > max ? `${flat.slice(0, max)}…` : flat;
}
