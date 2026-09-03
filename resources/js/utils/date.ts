export type DateValue = string | number | Date;

/** Age buckets for a reverse-chronological history list, newest first. */
export const HISTORY_BUCKETS = ['today', 'yesterday', 'last7Days', 'last30Days', 'older'] as const;

export type HistoryBucket = (typeof HISTORY_BUCKETS)[number];

/**
 * Buckets a date by how many local calendar days ago it was: `today`,
 * `yesterday`, within the last 7 or 30 days, or `older`. Future dates count as
 * `today`; invalid values fall into `older`.
 */
export function historyBucket(value: DateValue, now: Date = new Date()): HistoryBucket {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return 'older';

    // Calendar-day distance: the local date parts are re-anchored in UTC so a
    // DST shift between the two dates cannot skew the day count.
    const dayNumber = (d: Date) => Math.floor(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()) / 86_400_000);
    const daysAgo = dayNumber(now) - dayNumber(date);

    if (daysAgo <= 0) return 'today';
    if (daysAgo === 1) return 'yesterday';
    if (daysAgo <= 7) return 'last7Days';
    if (daysAgo <= 30) return 'last30Days';
    return 'older';
}

/**
 * Formats a date with its time. Dates from today omit the redundant date part.
 * Invalid values are returned unchanged as strings.
 */
export function formatDateTime(value: DateValue, locales?: Intl.LocalesArgument): string {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    const now = new Date();
    const isToday = date.getFullYear() === now.getFullYear()
        && date.getMonth() === now.getMonth()
        && date.getDate() === now.getDate();

    return new Intl.DateTimeFormat(locales, isToday
        ? {hour: '2-digit', minute: '2-digit'}
        : {dateStyle: 'short', timeStyle: 'short'}
    ).format(date);
}
