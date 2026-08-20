export type DateValue = string | number | Date;

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
