import z from 'zod';

/**
 * Validates the `announcements` API resource — server-side announcements addressed to the
 * current user (usage policies, feature notices, news, ...), combined with the per-user
 * acknowledgement state from the `announcement_user` pivot. Consumed by
 * `AnnouncementStore.svelte.ts`, which shows pending ones in a dialog on app load and
 * feeds the announcement history page.
 *
 * Registers the resource under the key `'announcements'` in `HawkiResourceSchemas` (see the
 * `declare module` augmentation below).
 */
const AnnouncementsSchema = z.object({
    id: z.string(),
    /** Internal identifier chosen by the announcement author (e.g. `guidelines`) — not meant for display; use the content's first heading instead. */
    title: z.string(),
    /** Category of the announcement, e.g. `policy`, `news`, `system`, `event` or `info`. Kept as a plain string so the backend can introduce new types without a frontend schema change. */
    type: z.string(),
    /** Forced announcements must be accepted; declining them logs the user out. */
    is_forced: z.boolean(),
    /** When set, the announcement is not shown on load but when the anchored feature is first used (e.g. `FileUpload`). */
    anchor: z.string().nullable(),
    starts_at: z.string().nullable(),
    expires_at: z.string().nullable(),
    /** True while the announcement is inside its `starts_at`/`expires_at` window; only active, unaccepted announcements are shown in the dialog. */
    is_active: z.boolean(),
    /** Localized markdown body. May contain `[CONFIRM](label)` / `[DECLINE](label)` button tags — see `parseAnnouncementContent`. */
    content: z.string(),
    /** When the current user first saw the announcement, or null. */
    seen_at: z.string().nullable(),
    /** When the current user accepted the announcement, or null. */
    accepted_at: z.string().nullable(),
    /** How many users have seen this announcement, across all users. */
    seen_count: z.number()
}).strict();

export default AnnouncementsSchema;

export type Announcement = z.infer<typeof AnnouncementsSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'announcements': Announcement;
    }
}
