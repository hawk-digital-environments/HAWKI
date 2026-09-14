import type {RouteComponent, RouteMeta, RouteRegistrar} from '$lib/components/ui/routing/index.js';
import type {WelcomeStep} from './welcomeSteps.js';

/** Meta every onboarding route carries, so the page knows which step it shows and where its neighbours are. */
export interface WelcomeStepMeta extends RouteMeta {
    step: WelcomeStep;
    /** Route name of the previous step; absent on the first step. */
    previous?: string;
    /** Route name of the next step; absent on the last step, whose action finishes the onboarding. */
    next?: string;
}

export function welcomeRouteName(step: WelcomeStep): string {
    return `welcome.${step}`;
}

/**
 * Registers one route per onboarding step on the sub-router, all rendered by
 * the same page component and linked through their meta. The first step also
 * answers the router's root path, which is where a freshly bound transient
 * router starts.
 */
export function registerWelcomeRoutes(registrar: RouteRegistrar, steps: WelcomeStep[], page: RouteComponent): void {
    steps.forEach((step, index) => {
        const meta: WelcomeStepMeta = {
            step,
            ...(index > 0 ? {previous: welcomeRouteName(steps[index - 1])} : {}),
            ...(index < steps.length - 1 ? {next: welcomeRouteName(steps[index + 1])} : {})
        };
        if (index === 0) registrar.route('/', page, {meta});
        registrar.route(`/${step}`, page, {name: welcomeRouteName(step), meta});
    });
}
