/**
 * Capitalises the first character of a string (equivalent to PHP's `Str::ucfirst`).
 */
export function ucfirst(str: string) {
    if (!str) return str;
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Replaces multiple substrings in one pass, preferring longer keys over shorter ones –
 * matching PHP's `strtr($str, $pairs)` behaviour.
 */
export function strtr(str: string, pairs: Record<string, string>) {
    // Sort keys longest-first so longer placeholders are matched preferentially
    const keys = Object.keys(pairs).sort((a, b) => b.length - a.length);
    let result = '';
    let i = 0;
    while (i < str.length) {
        let matched = false;
        for (const key of keys) {
            if (str.startsWith(key, i)) {
                result += pairs[key];
                i += key.length;
                matched = true;
                break;
            }
        }
        if (!matched) {
            result += str[i++];
        }
    }
    return result;
}

/**
 * Returns the last path segment of `path` (mirrors PHP's `basename()`).
 * Splits on both `/` and `\` so it works for POSIX and Windows-style paths.
 *
 * @example
 * basename('/uploads/reports/q1.pdf'); // 'q1.pdf'
 * basename('C:\\Users\\me\\file.txt'); // 'file.txt'
 */
export function basename(path: string): string {
    const parts = path.split(/[\\/]/);
    return parts[parts.length - 1];
}

/**
 * Converts an arbitrary string into a URL-safe slug: lowercased, German
 * umlauts/ß transliterated (ä→ae, ö→oe, ü→ue, ß→ss), remaining diacritics
 * stripped, and any run of non `a-z0-9` characters collapsed to a single `-`
 * (leading/trailing dashes trimmed).
 *
 * Used to derive stable route segments from plugin/module display names —
 * see `kernel/routing/routeInflection.ts`.
 *
 * @example
 * valueToSlug('Prüfungsübersicht'); // 'pruefungsuebersicht'
 * valueToSlug('My Plugin Name');    // 'my-plugin-name'
 */
export function valueToSlug(value: string): string {
    let slug = value.toLowerCase();
    slug = slug.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    slug = slug.replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss');
    slug = slug.replace(/[^a-z0-9]+/g, '-');
    slug = slug.replace(/^-+|-+$/g, '');
    return slug;
}
