import type {HawkiRouteContext, RouteResultBody} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

/**
 * Guard that runs *before* the route (or route group) it is attached to.
 *
 * Modeled on a classic PHP-style middleware stack: the callable receives the
 * route context — a {@link HawkiRouteContext}, so app-level services such as
 * `context.app` / `context.restApi` are reachable from a guard, same as from a
 * loader — and a `next` callback that resumes the guarded route. Only
 * the three return shapes below are meaningful — HAWKI does not expose
 * `universal-router`'s raw `null` vs. `undefined` action distinction to
 * middleware authors; the wrapper in {@link buildMiddlewareStack} normalises
 * them so callers never have to learn that nuance.
 *
 * - **Return a {@link RouteResultBody}** to take over rendering and stop
 *   resolution. The body carries `component`, `context` and `params`, so a
 *   middleware can both replace the page *and* rewrite the params the page
 *   will receive — e.g. inject a derived value, normalise a slug — before the
 *   router picks them up. `component` may be an eager component or a
 *   `lazyComponent()` loader; the router resolves it like any other node.
 * - **`return await next()`** to pass through to the guarded route. Whatever
 *   the guarded route resolves to is handed back unchanged.
 * - **Return nothing (or throw)** to mark the guarded route as unreachable:
 *   `universal-router` skips the route's subtree, falls through to the next
 *   sibling, and 404s if nothing else matches — the permission-deny signal.
 * - **`import {redirect, routeError} from './signals.js'` and call one** to
 *   send the user elsewhere, or fail the resolution with an HTTP-style
 *   status, instead of returning/throwing directly — see `signals.ts` and
 *   `buildMiddlewareStack.ts`'s module doc comment for why this exists
 *   alongside "return a replacement body": swapping the component alone
 *   leaves the URL pointing at a page the user isn't actually on.
 */
export type RouteMiddleware = (
    context: HawkiRouteContext,
    next: () => Promise<RouteResultBody | undefined>
) => Promise<RouteResultBody | undefined>;

/**
 * Set-up work a middleware wants to run *alongside* guarding, returning the
 * disposer that undoes it. Runs before the middleware body, so a listener is
 * already live while the guarded route's loaders are still resolving.
 *
 * The disposer is guaranteed to run exactly once, on whichever of these
 * happens first: the next navigation, this resolution failing/redirecting/
 * being superseded before it ever renders, or the `RouterView` unmounting.
 * Because it also runs for a resolution that never reaches the screen, an
 * effect must be cheap and invisible — subscribe to something, yes; open a
 * modal or fire a request, no.
 *
 * Reach the router through {@link HawkiRouteContext.ownerRouter}, not
 * `context.router` (which is `universal-router`'s own instance). Navigating
 * from inside the disposer's callback must go through that handle —
 * `redirect()` only works while a resolution is on the stack, so throwing it
 * from an event handler later is an unhandled rejection, not a redirect.
 *
 * @example
 * declareEffectfulMiddleware(
 *     async (context, next) => next(),
 *     (context) => context.app.events.async.on('connectionChanged', () => {
 *         if (context.app.connection.type !== 'internal_authenticated') {
 *             void context.ownerRouter.goToRoute('auth.login');
 *         }
 *     })
 * );
 */
export type MiddlewareEffect = (context: HawkiRouteContext) => (() => void) | void;

/** A {@link RouteMiddleware} carrying a {@link MiddlewareEffect}, as produced by {@link declareEffectfulMiddleware}. */
export type EffectfulMiddleware = RouteMiddleware & { effect: MiddlewareEffect };

/**
 * Tags `middleware` with an {@link MiddlewareEffect} the router runs (and
 * later disposes) around it.
 *
 * Returns a *fresh* function rather than tagging `middleware` in place: the
 * same guard is often reused across several routes, and mutating it would let
 * the last `declareEffectfulMiddleware()` call silently overwrite the effect
 * every earlier registration is still pointing at.
 */
export function declareEffectfulMiddleware(
    middleware: RouteMiddleware,
    effect: MiddlewareEffect
): EffectfulMiddleware {
    const effectfulMiddleware = ((context, next) => middleware(context, next)) as EffectfulMiddleware;
    effectfulMiddleware.effect = effect;
    return effectfulMiddleware;
}

/**
 * Whether `middleware` was produced by {@link declareEffectfulMiddleware}.
 * A middleware and an effectful one are both plain functions at runtime, so
 * the tag is the only thing telling them apart — same reason
 * `lazyComponent.ts` tags its loaders.
 */
export function isEffectfulMiddleware(middleware: RouteMiddleware): middleware is EffectfulMiddleware {
    return typeof (middleware as EffectfulMiddleware).effect === 'function';
}
