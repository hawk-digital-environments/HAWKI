import {hawkiConnection} from './hawkiConnection';

/**
 * Translates a label key into the corresponding localised string.
 *
 * Supports dot-notation for nested translation keys (e.g. `'section.subsection.key'`).
 *
 * Placeholder replacement is compatible with Laravel's `Translator::makeReplacements()`:
 * - **Function values** – when a replacement value is a `Function`, every occurrence of
 *   `<key>content</key>` in the string is replaced by the return value of `fn(content)`.
 * - **String values** – three variants are substituted simultaneously (longest-match-first,
 *   mirroring PHP's `strtr`):
 *   - `:KEY`  → value converted to upper-case
 *   - `:Key`  → value with first letter upper-cased
 *   - `:key`  → value as-is
 *
 * @param {string} label - Translation key, optionally using dot notation.
 * @param {Object.<string, string|Function>} [replacements={}] - Map of placeholder keys to
 *   replacement values or callback functions.
 * @returns {string} The translated, interpolated string, or a "Missing translation: …" message
 *   when the key cannot be found.
 */
export function translate(label, replacements = {}) {
    const labels = hawkiConnection('translation.labels');
    const resolvedLabel = findRecursively(labels, label);
    if (resolvedLabel === null) {
        console.log(labels);
        console.warn(`Translation for label "${label}" not found.`);
        return `Missing translation: ${label}`;
    }

    if (Object.keys(replacements).length === 0) {
        return resolvedLabel;
    }

    let finalLabel = resolvedLabel;
    const shouldReplace = {};

    for (const [key, value] of Object.entries(replacements)) {
        if (typeof value === 'function') {
            // Replace <key>inner</key> occurrences via the callback
            finalLabel = finalLabel.replace(
                new RegExp(`<${key}>(.*?)<\\/${key}>`, 'g'),
                (_, inner) => value(inner)
            );
            continue;
        }

        const strValue = (value ?? '') + '';
        // Longest keys must win → add all three variants; strtr() handles priority
        shouldReplace[`:${key.toUpperCase()}`] = strValue.toUpperCase();
        shouldReplace[`:${ucfirst(key)}`] = ucfirst(strValue);
        shouldReplace[`:${key}`] = strValue;
    }

    return strtr(finalLabel, shouldReplace);
}

/**
 * Returns `true` when a translation entry exists for the given key.
 *
 * @param {string} label - Translation key (supports dot notation).
 * @returns {boolean}
 */
export function hasTranslation(label) {
    const labels = hawkiConnection('translation.labels');
    return findRecursively(labels, label) !== null;
}

/**
 * Resolves a (possibly dot-notated) key inside a nested object.
 *
 * @param {Object} obj  - The object to search in.
 * @param {string} key  - Simple or dot-notated key (e.g. `'a.b.c'`).
 * @returns {*|null} The resolved value, or `null` when not found.
 */
function findRecursively(obj, key) {
    if (key.indexOf('.') > -1) {
        const parts = key.split('.');
        let current = obj;
        for (const part of parts) {
            if (current[part] === undefined) {
                return null;
            }
            current = current[part];
        }
        return current;
    } else {
        return obj[key] !== undefined ? obj[key] : null;
    }
}

/**
 * Capitalises the first character of a string (equivalent to PHP's `Str::ucfirst`).
 *
 * @param {string} str
 * @returns {string}
 */
function ucfirst(str) {
    if (!str) return str;
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Replaces multiple substrings in one pass, preferring longer keys over shorter ones –
 * matching PHP's `strtr($str, $pairs)` behaviour.
 *
 * @param {string} str                    - The source string.
 * @param {Object.<string, string>} pairs - Map of search-strings to replacement-strings.
 * @returns {string}
 */
function strtr(str, pairs) {
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
