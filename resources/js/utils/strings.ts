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

/** Scheme (`https://`, `ftp://`, …) or bare `www.` prefix that marks a URL token. */
const URL_PREFIX = /^(?:[a-z][a-z0-9+.-]*:\/\/|www\.)/i;
/** File extension at the end of a token, e.g. `.pdf`. */
const FILE_EXTENSION = /\.[a-z0-9]{1,8}$/i;
/** Sentence punctuation glued to the end of a token (`file.pdf:`, `(https://…)`). */
const TRAILING_PUNCTUATION = /[.,:;!?)\]}"'»]+$/;

/**
 * Shortens whitespace-free tokens longer than `maxLength` that look like URLs
 * or file names, so they can't blow up a narrow container (e.g. a toast).
 * URLs keep their scheme plus `keep` characters; file names keep `keep`
 * characters plus their extension. Other text is returned unchanged.
 *
 * @example
 * shortenLongTokens('Upload failed: a_really_long_report_name_from_2026_final_v2.pdf');
 * // 'Upload failed: a_really_long_report....pdf'
 * shortenLongTokens('See https://example.com/some/very/long/path/to/a/resource');
 * // 'See https://example.com/some/ver...'
 */
export function shortenLongTokens(text: string, maxLength = 40, keep = 20): string {
    return text.replace(/\S+/g, (token) => {
        if (token.length <= maxLength) return token;

        const trailing = token.match(TRAILING_PUNCTUATION)?.[0] ?? '';
        const core = token.slice(0, token.length - trailing.length);

        const prefix = core.match(URL_PREFIX)?.[0];
        if (prefix) {
            return `${prefix}${core.slice(prefix.length, prefix.length + keep)}...${trailing}`;
        }

        const extension = core.match(FILE_EXTENSION)?.[0];
        if (extension && core.length - extension.length > keep) {
            return `${core.slice(0, keep)}...${extension}${trailing}`;
        }

        return token;
    });
}
