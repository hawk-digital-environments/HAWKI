/**
 * True on Apple platforms (macOS, iOS, iPadOS), where the Command key takes
 * the role Ctrl has elsewhere. Evaluated once at module load; `false` outside
 * the browser (e.g. in tests without a DOM).
 */
export const isApple: boolean =
    typeof navigator !== 'undefined' && /mac|iphone|ipad|ipod/i.test(navigator.userAgent);
