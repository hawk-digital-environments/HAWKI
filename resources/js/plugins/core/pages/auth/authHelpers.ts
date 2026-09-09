import {ApiTransportError} from '$lib/kernel/api/errors.js';
import {sanitizeNext} from '$lib/kernel/auth/navigation.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';

export function nextDestination(): string | undefined {
    return sanitizeNext(new URLSearchParams(window.location.search).get('next'));
}

export function authErrorKey(error: unknown): string {
    return error instanceof ApiTransportError ? authErrorCodeKey(error.code) : 'ui.auth.errors.generic';
}

export function authErrorCodeKey(code: string | null | undefined): string {
    const translated = ['invalid_credentials', 'provider_failed', 'session_expired'];
    return code && translated.includes(code) ? `ui.auth.errors.${code}` : 'ui.auth.errors.generic';
}

export function goAfterUnlock(app: HawkiApp, next = nextDestination()): void {
    if (!app.cryptoReady) throw new Error('Keychain did not finish unlocking.');
    const destination = next ?? '/new/chat';
    void app.router.goTo(destination);
}
