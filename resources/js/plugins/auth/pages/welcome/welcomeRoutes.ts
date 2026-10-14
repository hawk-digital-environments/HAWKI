import type {RouteComponent, RouteMeta, RouteRegistrar} from '$lib/components/ui/routing/index.js';
import type {BuiltinWelcomeStep, CustomWelcomeStep, WelcomeStepDefinition, WelcomeStepOverrides} from './welcomeSteps.js';

/** Meta every onboarding route carries, so the page knows which step it shows and where its neighbours are. */
export interface WelcomeStepMeta extends RouteMeta {
    /** Route segment of the step. */
    step: string;
    /** The built-in step it shows, with any texts an administrator gave it, or */
    builtin?: BuiltinWelcomeStep;
    overrides?: WelcomeStepOverrides;
    /** the step an administrator wrote. */
    custom?: CustomWelcomeStep;
    /** Zero-based position of the step, for the progress indicator. */
    index: number;
    /** Number of steps in the onboarding. */
    total: number;
    /** Route name of the previous step; absent on the first step. */
    previous?: string;
    /** Route name of the next step; absent on the last step, whose action finishes the onboarding. */
    next?: string;
}

export function welcomeRouteName(step: string): string {
    return `welcome.${step}`;
}

/**
 * Registers one route per onboarding step on the sub-router, all rendered by
 * the same page component and linked through their meta. The first step also
 * answers the router's root path, which is where a freshly bound transient
 * router starts.
 */
export function registerWelcomeRoutes(registrar: RouteRegistrar, steps: WelcomeStepDefinition[], page: RouteComponent): void {
    steps.forEach((step, index) => {
        const meta: WelcomeStepMeta = {
            step: step.id,
            ...(step.builtin ? {builtin: step.builtin} : {}),
            ...(step.overrides ? {overrides: step.overrides} : {}),
            ...(step.custom ? {custom: step.custom} : {}),
            index,
            total: steps.length,
            ...(index > 0 ? {previous: welcomeRouteName(steps[index - 1].id)} : {}),
            ...(index < steps.length - 1 ? {next: welcomeRouteName(steps[index + 1].id)} : {})
        };
        if (index === 0) registrar.route('/', page, {meta});
        registrar.route(`/${step.id}`, page, {name: welcomeRouteName(step.id), meta});
    });
}
