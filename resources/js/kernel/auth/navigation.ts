/** Accept only paths inside the SPA, including after URL decoding and normalization. */
export function sanitizeNext(value: unknown): string | undefined {
    if (typeof value !== 'string' || !value || value.length > 512) return undefined;
    let decoded = value;
    try {
        // Reject nested escapes as well: proxies and redirects can decode again.
        for (let i = 0; i < 5; i++) {
            const next = decodeURIComponent(decoded);
            if (next === decoded) break;
            decoded = next;
        }
        if (/%[0-9a-f]{2}/i.test(decoded) || !decoded.startsWith('/new/') || /[\\\x00-\x1f\x7f]/.test(decoded)) return undefined;
        const url = new URL(decoded, 'https://hawki.invalid');
        if (url.origin !== 'https://hawki.invalid' || !url.pathname.startsWith('/new/')) return undefined;
        return url.pathname + url.search + url.hash;
    } catch {
        return undefined;
    }
}

export type AuthPage = 'login' | 'handshake' | 'register' | 'inconsistent';

export function authPageUrl(page: AuthPage, next?: unknown): string {
    const destination = sanitizeNext(next);
    return `/new/auth/${page}${destination ? `?${new URLSearchParams({next: destination})}` : ''}`;
}

export function currentNext(pathname = window.location.pathname): string | undefined {
    if (pathname.startsWith('/new/auth/')) {
        return sanitizeNext(new URLSearchParams(window.location.search).get('next'));
    }
    return sanitizeNext(pathname + window.location.search + window.location.hash);
}

/** Navigate to an auth page without turning the current auth URL into a nested `next`. */
export function assignAuthPage(page: AuthPage, next: unknown = currentNext()): boolean {
    const target = authPageUrl(page, next);
    const current = window.location.pathname + window.location.search + window.location.hash;
    if (target === current) return false;
    window.location.assign(target);
    return true;
}
