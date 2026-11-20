/**
 * The one review-state → tone mapping, shared by every surface that renders
 * a review status (the admin queue and detail flow, the dashboard cards and
 * detail page). Both StatusPill and Alert style review states, but their
 * tone vocabularies differ — Alert has no "safe", so an approval reads as
 * the neutral info box there.
 *
 * Keyed by plain string (enum values and string unions both index safely);
 * an unknown status falls back to the neutral tones.
 */

/** The tones {@link StatusPill} accepts (see StatusPill.svelte). */
export type ReviewPillTone = 'info' | 'safe' | 'warning' | 'error';

/** The tones the Alert component accepts (Alert has no "safe"). */
export type ReviewAlertTone = 'neutral' | 'info' | 'warning' | 'error';

const PILL_TONES: Record<string, ReviewPillTone> = {
    pending: 'warning',
    approved: 'safe',
    needs_revision: 'error',
    denied: 'error'
};

const ALERT_TONES: Record<string, ReviewAlertTone> = {
    pending: 'warning',
    approved: 'neutral',
    needs_revision: 'error',
    denied: 'error'
};

/** StatusPill's tone for a review status (pill form: approved = safe). */
export function reviewPillTone(status: string): ReviewPillTone {
    return PILL_TONES[status] ?? 'info';
}

/** Alert's tone for a review status (info box form: approved = neutral). */
export function reviewAlertTone(status: string): ReviewAlertTone {
    return ALERT_TONES[status] ?? 'neutral';
}
