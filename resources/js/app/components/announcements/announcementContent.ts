/**
 * Helpers for rendering announcement markdown bodies.
 *
 * Announcement content files (`resources/announcements/{view}/{lang}.md`) may embed
 * button labels as `[CONFIRM](label)` / `[DECLINE](label)` tags — the same convention
 * the legacy UI used. The tags are stripped from the rendered body and surfaced as
 * labels for the dialog's action buttons.
 */

const CONFIRM_TAG_REGEX = /\[CONFIRM]\(([^)]+)\)/i;
const DECLINE_TAG_REGEX = /\[DECLINE]\(([^)]+)\)/i;
const HEADING_REGEX = /^#{1,6}\s+(.+)$/m;

export interface ParsedAnnouncementContent {
    /** The markdown body with the button tags removed. */
    body: string;
    /** Label of the confirm button as defined in the content, or null to use the default label. */
    confirmLabel: string | null;
    /** Label of the decline button as defined in the content, or null to use the default label. */
    declineLabel: string | null;
}

export function parseAnnouncementContent(content: string): ParsedAnnouncementContent {
    const confirmLabel = CONFIRM_TAG_REGEX.exec(content)?.[1].trim() ?? null;
    const declineLabel = DECLINE_TAG_REGEX.exec(content)?.[1].trim() ?? null;

    return {
        body: content.replace(CONFIRM_TAG_REGEX, '').replace(DECLINE_TAG_REGEX, '').trim(),
        confirmLabel,
        declineLabel
    };
}

/**
 * Human-readable label for an announcement: the first markdown heading of its
 * content, falling back to the internal `title` slug when there is none.
 */
export function announcementDisplayTitle(announcement: { title: string; content: string }): string {
    return HEADING_REGEX.exec(announcement.content)?.[1].trim() ?? announcement.title;
}

/**
 * Removes a heading from the start of a markdown body. Used where the heading
 * is already rendered separately (see {@link announcementDisplayTitle}), e.g.
 * as the card title on the announcements page, so it doesn't appear twice.
 */
export function stripLeadingHeading(body: string): string {
    return body.replace(/^\s*#{1,6}\s+.+(\r?\n|$)/, '').trim();
}
