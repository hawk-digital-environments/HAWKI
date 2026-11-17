import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {mayDecideReviews} from './api/reviewClient.js';

/**
 * Session-scoped answer to "may this user decide assistant reviews?": the
 * `actions/permissions` probe, fetched once at boot while signed in. `null`
 * means unanswered; consumers treat it as hidden without flashing a
 * no-access state. The per-review policies remain the security boundary.
 */
let allowed = $state<boolean | null>(null);
let probe: Promise<void> | null = null;

export function mayReviewAssistants(): boolean | null {
    return allowed;
}

/**
 * Probes once per session (idempotent). Fails closed: a rejected probe
 * counts as denied until the next page load.
 */
export function probeReviewPermission(app: HawkiApp): void {
    if (!app.connection.isAuthenticated) {
        return;
    }

    probe ??= mayDecideReviews()
        .then((verdict): void => {
            allowed = verdict;
        })
        .catch((): void => {
            allowed = false;
        });
}
