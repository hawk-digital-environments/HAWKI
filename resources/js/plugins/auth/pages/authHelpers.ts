import {ApiTransportError} from '$lib/kernel/api/errors.js';
import {sanitizeNext} from '$lib/kernel/auth/navigation.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';

export function nextDestination(app: HawkiApp): string | undefined {
    return sanitizeNext(app.router, new URLSearchParams(window.location.search).get('next'));
}

export function authErrorKey(error: unknown): string {
    return error instanceof ApiTransportError ? authErrorCodeKey(error.code) : 'ui.auth.errors.generic';
}

export function authErrorCodeKey(code: string | null | undefined): string {
    const translated = ['invalid_credentials', 'provider_failed', 'session_expired', 'auth_redirect_required', 'registration_policy_unavailable'];
    return code && translated.includes(code) ? `ui.auth.errors.${code}` : 'ui.auth.errors.generic';
}

export function goAfterUnlock(app: HawkiApp, next = nextDestination(app)): void {
    if (!app.cryptoReady) throw new Error('Keychain did not finish unlocking.');
    const destination = next ?? app.router.getPath('chat.index');
    void app.router.goTo(destination);
}

export function loginReason(value: unknown): 'session_expired' | undefined {
    return value === 'session_expired' ? value : undefined;
}

export function registrationErrorPage(code: string | undefined): 'inconsistent' | 'login' | undefined {
    if (code === 'registration_keychain_inconsistent') return 'inconsistent';
    if (code === 'registration_not_in_progress' || code === 'registration_already_completed') return 'login';
}
