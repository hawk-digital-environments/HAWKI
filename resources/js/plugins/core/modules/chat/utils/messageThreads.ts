import type {ChatMessage} from '$plugins/core/modules/chat/types.js';

/**
 * A trunk message together with the replies of its thread.
 *
 * Message ids follow the legacy `W.DDD` scheme: `12.000` is the twelfth trunk
 * message, `12.003` is the third reply in its thread. Client-only messages
 * (pending/streaming) carry no parseable id yet and use the `threadId` field
 * set by the transport instead.
 */
export interface ChatThreadGroup {
    message: ChatMessage;
    replies: ChatMessage[];
}

/**
 * The thread a message belongs to: `0` for trunk messages, otherwise the
 * whole number of the trunk message that owns the thread.
 */
export function threadIndexOf(message: ChatMessage): number {
    if (typeof message.threadId === 'number' && Number.isFinite(message.threadId)) {
        return message.threadId;
    }
    const [whole, decimal] = message.message_id.split('.').map(Number);
    if (!Number.isFinite(whole) || !Number.isFinite(decimal) || decimal === 0) {
        return 0;
    }
    return whole;
}

/**
 * The whole number of a persisted message id (`"12.003"` → `12`), or `null`
 * for client-only ids (`pending-…`/`stream-…`) that cannot anchor a thread yet.
 */
export function messageTrunkId(message: ChatMessage): number | null {
    const whole = Number(message.message_id.split('.')[0]);
    return Number.isFinite(whole) ? whole : null;
}

/**
 * Groups a flat, creation-ordered message list into trunk messages with their
 * thread replies nested under them. Replies whose trunk message is missing
 * (e.g. it was deleted) are appended as trunk messages so they stay visible.
 */
export function groupMessagesIntoThreads(messages: ChatMessage[]): ChatThreadGroup[] {
    const groups: ChatThreadGroup[] = [];
    const trunkById = new Map<number, ChatThreadGroup>();
    const orphans: ChatMessage[] = [];

    for (const message of messages) {
        const threadIndex = threadIndexOf(message);
        if (threadIndex === 0) {
            const group: ChatThreadGroup = {message, replies: []};
            const trunkId = messageTrunkId(message);
            if (trunkId !== null) trunkById.set(trunkId, group);
            groups.push(group);
        } else {
            const parent = trunkById.get(threadIndex);
            if (parent) parent.replies.push(message);
            else orphans.push(message);
        }
    }

    for (const message of orphans) {
        groups.push({message, replies: []});
    }

    return groups;
}
