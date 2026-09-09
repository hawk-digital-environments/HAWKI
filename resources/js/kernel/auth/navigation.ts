import type {RouterHandle} from '$lib/components/ui/routing/index.js';

export type AuthRouter = Pick<RouterHandle, 'getPath'>;

/** Accept only paths inside the SPA, including after URL decoding and normalization. */
export function sanitizeNext(router: AuthRouter, value: unknown): string | undefined {
    if (typeof value !== 'string' || !value || value.length > 512) return undefined;
    const prefix = router.getPath('/').replace(/\/+$/, '') + '/';
    let decoded = value;
    try {
        // Reject nested escapes as well: proxies and redirects can decode again.
        for (let i = 0; i < 5; i++) {
            const next = decodeURIComponent(decoded);
            if (next === decoded) break;
            decoded = next;
        }
        if (/%[0-9a-f]{2}/i.test(decoded) || !decoded.startsWith(prefix) || /[\\\x00-\x1f\x7f]/.test(decoded)) return undefined;
        const url = new URL(decoded, 'https://hawki.invalid');
        if (url.origin !== 'https://hawki.invalid' || !url.pathname.startsWith(prefix)) return undefined;
        return url.pathname + url.search + url.hash;
    } catch {
        return undefined;
    }
}

export type AuthPage = 'login' | 'handshake' | 'register' | 'inconsistent';

export function authPageUrl(router: AuthRouter, page: AuthPage, next?: unknown, reason?: unknown): string {
    const destination = sanitizeNext(router, next);
    return router.getPath(`auth.${page}`, {
        ...(destination ? {next: destination} : {}),
        ...(page === 'login' && reason === 'session_expired' ? {reason} : {})
    });
}

export function currentNext(router: AuthRouter, pathname = window.location.pathname): string | undefined {
    const authPages: AuthPage[] = ['login', 'handshake', 'register', 'inconsistent'];
    if (authPages.some(page => pathname === router.getPath(`auth.${page}`))) {
        return sanitizeNext(router, new URLSearchParams(window.location.search).get('next'));
    }
    return sanitizeNext(router, pathname + window.location.search + window.location.hash);
}

/** Preserve the original destination when moving between auth pages. */
export function assignAuthPage(router: AuthRouter, page: AuthPage, next: unknown = currentNext(router), reason?: unknown): boolean {
    const target = authPageUrl(router, page, next, reason);
    const current = window.location.pathname + window.location.search + window.location.hash;
    if (target === current) return false;
    window.location.assign(target);
    return true;
}
