import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {Announcement} from '$plugins/core/schemas/resources/announcements.schema.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        announcements: AnnouncementStore;
    }
}

/** Anchor name of announcements to show when the user first attaches a file (see the `first-upload` announcement). */
export const FILE_UPLOAD_ANNOUNCEMENT_ANCHOR = 'FileUpload';

/**
 * Reactive store for server-side announcements addressed to the current user.
 *
 * Populated by {@link AnnouncementStore.loadData} during bootstrap (authenticated
 * connections only). Unanchored pending announcements (active window, not yet
 * accepted) are queued right away, so `AnnouncementDialog` — which renders
 * {@link current} — shows them on page load. Anchored ones stay dormant until a
 * feature calls {@link triggerAnchor} (e.g. the chat composer when a file is
 * attached). The full list feeds the announcement history page.
 *
 * @example
 * import {useStore} from '$lib/app/hooks/useStore.svelte.js';
 * const announcementStore = useStore('announcements');
 * announcementStore.triggerAnchor(FILE_UPLOAD_ANNOUNCEMENT_ANCHOR);
 */
export class AnnouncementStore implements DataStore {
    public readonly name = 'announcements';

    private app: HawkiApp | null = null;

    /** All announcements addressed to the current user, newest first as returned by the API. */
    public announcements = $state([] as Announcement[]);

    /** Ids queued for display in the announcement dialog; the first entry is the one showing. */
    private queuedIds = $state([] as string[]);

    /** The announcement the dialog should currently display, or null when the queue is empty. */
    public current = $derived.by(() =>
        this.announcements.find(announcement => announcement.id === this.queuedIds[0]) ?? null
    );

    public async loadData(app: HawkiApp): Promise<void> {
        if (!app.connection.isAuthenticated) {
            return;
        }

        this.app = app;
        this.announcements = await app.restApi.getResourceCollection('announcements');
        this.enqueue(this.prioritize(this.pendingWithAnchor(null)));
    }

    /**
     * Queues the pending announcements bound to the given anchor for display.
     * A no-op for anchors without pending announcements, so features can call
     * this unconditionally whenever the anchored interaction happens.
     */
    public triggerAnchor(anchor: string): void {
        this.enqueue(this.prioritize(this.pendingWithAnchor(anchor)));
    }

    /**
     * Display priority for the dialog queue: every forced announcement first,
     * followed by only the newest non-forced one — older unaccepted news stays
     * off the dialog and remains readable on the announcements page. Relies on
     * the API order (newest first, see `UserAnnouncementRepository`).
     */
    private prioritize(pending: Announcement[]): Announcement[] {
        const forced = pending.filter(announcement => announcement.is_forced);
        const newestNonForced = pending.find(announcement => !announcement.is_forced);
        return newestNonForced ? [...forced, newestNonForced] : forced;
    }

    /** Marks the announcement as seen on the server and mirrors the timestamp locally. */
    public async markSeen(announcement: Announcement): Promise<void> {
        this.patch(announcement.id, {seen_at: announcement.seen_at ?? new Date().toISOString()});
        await this.getApp().restApi.postToResourceAction('announcements', 'actions/seen', {
            announcement_id: Number(announcement.id)
        });
    }

    /** Accepts the announcement on the server, then removes it from the display queue. */
    public async accept(announcement: Announcement): Promise<void> {
        await this.getApp().restApi.postToResourceAction('announcements', 'actions/accept', {
            announcement_id: Number(announcement.id)
        });
        this.patch(announcement.id, {accepted_at: new Date().toISOString()});
        this.dequeue(announcement.id);
    }

    /** Removes the announcement from the display queue without accepting it, so it reappears on the next load. */
    public dismiss(announcement: Announcement): void {
        this.dequeue(announcement.id);
    }

    private pendingWithAnchor(anchor: string | null): Announcement[] {
        return this.announcements.filter(announcement =>
            announcement.is_active
            && announcement.accepted_at === null
            && announcement.anchor === anchor
        );
    }

    private enqueue(announcements: Announcement[]): void {
        const newIds = announcements
            .map(announcement => announcement.id)
            .filter(id => !this.queuedIds.includes(id));
        if (newIds.length > 0) {
            this.queuedIds = [...this.queuedIds, ...newIds];
        }
    }

    private dequeue(id: string): void {
        this.queuedIds = this.queuedIds.filter(queuedId => queuedId !== id);
    }

    private patch(id: string, changes: Partial<Announcement>): void {
        this.announcements = this.announcements.map(announcement =>
            announcement.id === id ? {...announcement, ...changes} : announcement
        );
    }

    private getApp(): HawkiApp {
        if (!this.app) {
            throw new Error('AnnouncementStore is not loaded yet.');
        }
        return this.app;
    }
}
