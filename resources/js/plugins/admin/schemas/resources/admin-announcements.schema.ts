import z from 'zod';
import { jsonObject } from './values.js';

/** Announcement rows from AnnouncementSchema.php and AnnouncementRepository.php. */
export const AdminAnnouncementSchema = z.object({
    id: z.string(),
    title: z.string(),
    kind: z.enum(['news', 'system', 'event', 'info', 'policy']),
    is_published: z.boolean(),
    starts_at: z.string().nullable(),
    expires_at: z.string().nullable(),
    seen_count: z.number(),
    accepted_count: z.number(),
    is_global: z.boolean(),
    is_forced: z.boolean(),
    target_roles: z.array(z.number()).nullable(),
    anchor: z.string().nullable(),
    /** Localized Markdown, resolved from legacy announcement content when necessary. */
    content: jsonObject(z.string()),
    /** Version for `If-Match` on writes; taken from the resource meta by `adminContent()`, not an attribute. */
    _version: z.string().optional()
});
export default AdminAnnouncementSchema;
/** A row of the section: the resource attributes plus the edit version `adminContent()` copies from the meta. */
export type AdminAnnouncementResource = z.infer<typeof AdminAnnouncementSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'admin-announcements': AdminAnnouncementResource;
    }
}
