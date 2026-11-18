/**
 * Easing curves for JavaScript animation code — the JS counterparts of the
 * CSS motion tokens in `resources/css/tokens/transitions.css`.
 *
 * Web Animations API options and inline styles cannot reference `var(…)`, so
 * every place that animates from JS needs the curve's literal value. Keep the
 * numbers here in sync with the token file: each constant names the token it
 * mirrors.
 */

/** Control points of `--easing-spring`, for easing math that solves the curve. */
export const SPRING_CONTROL_POINTS = [0.22, 1, 0.36, 1] as const;

/** `--easing-spring` as a WAAPI / inline-style easing string. */
export const EASING_SPRING = `cubic-bezier(${SPRING_CONTROL_POINTS.join(', ')})`;
