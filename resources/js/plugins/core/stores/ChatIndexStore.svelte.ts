import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {untrack} from 'svelte';
import {decryptSymmetric, encryptSymmetric, loadSymmetricCryptoValueFromObject} from '$lib/kernel/encryption/symmetric.js';
import type {ChatConversation, ChatSummary} from '$plugins/core/modules/chat/types.js';
import type {ChatStore} from '$plugins/core/stores/ChatStore.svelte.js';
import type {ExperimentsStore} from '$plugins/core/stores/ExperimentsStore.svelte.js';
import type {KeychainStore} from '$plugins/core/stores/KeychainStore.svelte.js';
import {
    isIndexStorageAvailable,
    readIndexFile,
    removeIndexFile,
    requestPersistentStorage,
    writeIndexFile
} from '$plugins/core/modules/chat/utils/chatIndexFile.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'chat-index': ChatIndexStore;
    }
}

/** The searchable part of one message. */
export interface ChatIndexMessage {
    id: string;
    role: 'user' | 'assistant';
    text: string;
    created_at: string;
}

/** One indexed conversation. */
export interface ChatIndexEntry {
    slug: string;
    name: string;
    messages: ChatIndexMessage[];
}

/** One indexed message together with the conversation it belongs to. */
export interface ChatIndexListEntry {
    slug: string;
    name: string;
    message: ChatIndexMessage;
}

/** On-disk shape of the (decrypted) index document. */
export interface ChatIndexDocument {
    version: 1;
    /** When the last full rebuild finished; `null` if the index only grew from opened chats. */
    builtAt: string | null;
    conversations: Record<string, ChatIndexEntry>;
}

const DOCUMENT_VERSION = 1;
const WRITE_DELAY_MS = 500;
const REBUILD_CONCURRENCY = 3;

/**
 * The "Chat Index" experiment: a local, encrypted JSON index over the text
 * of every chat message. The chat module contributes {@link messages} to the
 * search palette as a static group, so the palette's own index finds
 * *content*, not just conversation titles.
 *
 * The index fills itself while the user works — whenever a conversation is
 * opened, or a message in the active one has been persisted, that
 * conversation's messages are (re)written into the index — and can be built
 * for all conversations at once with {@link rebuild}, which fetches every
 * chat from the server. Deleted chats are pruned and renamed ones updated
 * from the chat store's summaries.
 *
 * Persistence goes through `utils/chatIndexFile.ts` (OPFS, or IndexedDB
 * where OPFS cannot be written) after asking for persistent storage. The
 * document is encrypted with the user's conversation key before it is
 * written, so the plaintext of messages is never at rest in the browser
 * unencrypted — the same guarantee the server-side storage gives.
 *
 * Everything is gated on the `chatIndex` experiment flag: while it is off,
 * nothing is read, written, or indexed.
 *
 * @example
 * const chatIndex = useStore('chat-index');
 * chatIndex.messages; // [{slug, name, message: {id, role, text, created_at}}, …]
 */
export class ChatIndexStore implements DataStore {
    public readonly name = 'chat-index';

    /** Whether the persisted index has been read (or found absent). */
    public loaded = $state(false);
    /** Whether a full rebuild is running. */
    public building = $state(false);
    /** Rebuild progress: conversations fetched so far and the total. */
    public progress = $state({done: 0, total: 0});
    /** The failure of the last rebuild or write, if any. */
    public error = $state<string | null>(null);
    /** Browser's answer to `navigator.storage.persist()`; `null` before it was asked or if unsupported. */
    public persistent = $state<boolean | null>(null);
    /** Whether the browser has any storage backend for the index at all. */
    public storageAvailable = $state(true);
    /** When the last full rebuild finished. */
    public builtAt = $state<string | null>(null);
    /** Hides the build prompt for this page load. */
    public dismissed = $state(false);

    private entries = $state<Record<string, ChatIndexEntry>>({});

    private app: HawkiApp | null = null;
    private writeTimer: ReturnType<typeof setTimeout> | null = null;

    public async loadData(app: HawkiApp): Promise<void> {
        this.app = app;
        try {
            app.authenticatedConnection;
        } catch {
            return;
        }
        this.storageAvailable = isIndexStorageAvailable();

        $effect.root(() => {
            // Load lazily: nothing is read from disk until the experiment is on.
            $effect(() => {
                if (!this.enabled || this.loaded) return;
                untrack(() => void this.load());
            });

            // Index the active conversation when it is opened and whenever its
            // messages change — but not while a message is still pending or
            // streaming, so only persisted content gets in.
            $effect(() => {
                if (!this.enabled || !this.loaded) return;
                const active = this.chatStore.active;
                if (!active) return;
                const messages = active.messages;
                if (messages.some(message => message.isPending || message.isStreaming)) return;
                untrack(() => this.indexConversation(active));
            });

            // Follow renames and deletions from the summaries list. Pruning is
            // skipped while the list is empty: before the first `refresh` the
            // store legitimately has no conversations yet.
            $effect(() => {
                if (!this.enabled || !this.loaded || this.chatStore.listLoading) return;
                const summaries = this.chatStore.conversations;
                untrack(() => this.syncSummaries(summaries));
            });
        });
    }

    /** Whether the `chatIndex` experiment is on. Reactive. */
    public get enabled(): boolean {
        return this.experiments.isEnabled('chatIndex');
    }

    /** Whether a full index exists, i.e. a rebuild has finished at some point. */
    public get hasIndex(): boolean {
        return this.builtAt !== null;
    }

    public get conversationCount(): number {
        return Object.keys(this.entries).length;
    }

    public get messageCount(): number {
        return Object.values(this.entries).reduce((sum, entry) => sum + entry.messages.length, 0);
    }

    /**
     * The experiment is on, the user already has several chats, but the
     * index is still completely empty — the moment to offer building it.
     * Once any conversation is indexed (opened or built) the prompt goes
     * away; a full rebuild stays available from the settings page.
     */
    public get needsBuild(): boolean {
        return this.enabled
            && this.loaded
            && this.storageAvailable
            && !this.building
            && !this.dismissed
            && this.conversationCount === 0
            && this.chatStore.conversations.length > 1;
    }

    /**
     * Every indexed message with its conversation, in index order (messages
     * in chat order). Empty while the experiment is off. Reactive.
     */
    public get messages(): ChatIndexListEntry[] {
        if (!this.enabled) return [];
        return Object.values(this.entries).flatMap(entry =>
            entry.messages.map(message => ({slug: entry.slug, name: entry.name, message}))
        );
    }

    /** Writes (or rewrites) one conversation's messages into the index. */
    public indexConversation(conversation: ChatConversation): void {
        const entry: ChatIndexEntry = {
            slug: conversation.slug,
            name: conversation.name,
            messages: conversation.messages
                .filter(message => !message.isPending && !message.isStreaming && message.content.text.trim() !== '')
                .map(message => ({
                    id: message.message_id,
                    role: message.message_role,
                    text: message.content.text,
                    created_at: message.created_at
                }))
        };
        const current = this.entries[conversation.slug];
        if (current && JSON.stringify(current) === JSON.stringify(entry)) return;
        this.entries = {...this.entries, [conversation.slug]: entry};
        this.scheduleWrite();
    }

    /** Drops a conversation from the index; a no-op if it is not indexed. */
    public removeConversation(slug: string): void {
        if (!(slug in this.entries)) return;
        const {[slug]: _removed, ...rest} = this.entries;
        this.entries = rest;
        this.scheduleWrite();
    }

    /**
     * Fetches every conversation the chat store lists from the server and
     * indexes it, a few at a time; conversations no longer listed are
     * dropped. Progress is exposed reactively. A conversation that fails to
     * load is skipped and the run continues; the first failure is kept in
     * `error`.
     */
    public async rebuild(): Promise<void> {
        if (this.building || !this.enabled) return;
        this.building = true;
        this.error = null;
        try {
            if (!this.loaded) await this.load();
            await this.chatStore.refresh();
            const summaries = [...this.chatStore.conversations];
            this.progress = {done: 0, total: summaries.length};

            const listed = new Set(summaries.map(summary => summary.slug));
            for (const slug of Object.keys(this.entries)) {
                if (!listed.has(slug)) this.removeConversation(slug);
            }

            const queue = [...summaries];
            const worker = async () => {
                for (let next = queue.shift(); next; next = queue.shift()) {
                    try {
                        this.indexConversation(await this.chatStore.fetch(next.slug));
                    } catch (error) {
                        console.error(`Chat index: failed to load "${next.slug}"`, error);
                        this.error ??= error instanceof Error ? error.message : String(error);
                    } finally {
                        this.progress = {...this.progress, done: this.progress.done + 1};
                    }
                }
            };
            await Promise.all(Array.from({length: Math.min(REBUILD_CONCURRENCY, queue.length)}, worker));

            this.builtAt = new Date().toISOString();
            await this.write();
        } catch (error) {
            console.error('Chat index: rebuild failed', error);
            this.error = error instanceof Error ? error.message : String(error);
        } finally {
            this.building = false;
        }
    }

    /** A plain, decrypted snapshot of the index as it would be written to storage. */
    public toDocument(): ChatIndexDocument {
        return {
            version: DOCUMENT_VERSION,
            builtAt: this.builtAt,
            conversations: $state.snapshot(this.entries)
        };
    }

    /** Deletes the index from storage and memory. */
    public async clear(): Promise<void> {
        this.cancelScheduledWrite();
        this.entries = {};
        this.builtAt = null;
        this.error = null;
        try {
            await removeIndexFile();
        } catch (error) {
            console.error('Chat index: failed to remove the index', error);
            this.error = error instanceof Error ? error.message : String(error);
        }
    }

    private async load(): Promise<void> {
        try {
            this.persistent = await requestPersistentStorage();
            const raw = await readIndexFile();
            if (raw) {
                const document = await this.decode(raw);
                if (document) {
                    this.entries = document.conversations;
                    this.builtAt = document.builtAt;
                }
            }
        } catch (error) {
            console.error('Chat index: failed to read the index', error);
            this.error = error instanceof Error ? error.message : String(error);
        } finally {
            this.loaded = true;
        }
    }

    private syncSummaries(summaries: ChatSummary[]): void {
        let changed = false;
        let entries = this.entries;
        const listed = new Map(summaries.map(summary => [summary.slug, summary]));

        for (const [slug, entry] of Object.entries(entries)) {
            const summary = listed.get(slug);
            if (!summary) {
                if (summaries.length === 0) continue;
                const {[slug]: _removed, ...rest} = entries;
                entries = rest;
                changed = true;
            } else if (summary.name !== entry.name) {
                entries = {...entries, [slug]: {...entry, name: summary.name}};
                changed = true;
            }
        }

        if (!changed) return;
        this.entries = entries;
        this.scheduleWrite();
    }

    private scheduleWrite(): void {
        this.cancelScheduledWrite();
        this.writeTimer = setTimeout(() => {
            this.writeTimer = null;
            void this.write();
        }, WRITE_DELAY_MS);
    }

    private cancelScheduledWrite(): void {
        if (this.writeTimer !== null) clearTimeout(this.writeTimer);
        this.writeTimer = null;
    }

    private async write(): Promise<void> {
        this.cancelScheduledWrite();
        try {
            await writeIndexFile(await this.encode(this.toDocument()));
        } catch (error) {
            console.error('Chat index: failed to write the index', error);
            this.error = error instanceof Error ? error.message : String(error);
        }
    }

    private async encode(document: ChatIndexDocument): Promise<string> {
        const encrypted = await encryptSymmetric(JSON.stringify(document), await this.conversationKey());
        return JSON.stringify(encrypted.toObject());
    }

    /** `null` for a document that cannot be decrypted or has another version — it is rebuilt from scratch. */
    private async decode(raw: string): Promise<ChatIndexDocument | null> {
        try {
            const plain = await decryptSymmetric(loadSymmetricCryptoValueFromObject(JSON.parse(raw)), await this.conversationKey());
            const document = JSON.parse(plain);
            if (document?.version !== DOCUMENT_VERSION || typeof document.conversations !== 'object') return null;
            return document as ChatIndexDocument;
        } catch (error) {
            console.warn('Chat index: stored index is unreadable and will be rebuilt', error);
            return null;
        }
    }

    private async conversationKey(): Promise<CryptoKey> {
        const keychain: KeychainStore = this.requireApp().stores.get('keychain');
        await keychain.waitingToLoad;
        if (!keychain.aiConvKey) throw new Error('The encrypted chat key is unavailable. Please sign in again.');
        return keychain.aiConvKey;
    }

    private get chatStore(): ChatStore {
        return this.requireApp().stores.get('chat');
    }

    private get experiments(): ExperimentsStore {
        return this.requireApp().stores.get('experiments');
    }

    private requireApp(): HawkiApp {
        if (!this.app) throw new Error('The chat index store has not been initialised.');
        return this.app;
    }
}
