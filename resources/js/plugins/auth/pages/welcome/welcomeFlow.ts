import {createHmrSafeContext} from '$lib/utils/hmrSafeContext.js';

/**
 * What the onboarding container hands its step pages: how to leave the
 * onboarding, and a one-shot focus request so a step mounted after an in-flow
 * navigation moves focus to its heading (the nested router leaves focus alone),
 * while the very first step rendered on page load does not steal it.
 */
export interface WelcomeFlow {
    /** Leaves the onboarding and continues with the keychain setup. */
    finish(): void;
    /** Marks that the next step mounting was reached by in-flow navigation. */
    requestFocus(): void;
    /** Whether a focus request is pending; clears it. */
    consumeFocusRequest(): boolean;
}

export const [useWelcomeFlow, provideWelcomeFlow] = createHmrSafeContext<WelcomeFlow>('hawki.auth.registration-welcome');

export function createWelcomeFlow(finish: () => void): WelcomeFlow {
    let focusRequested = false;
    return {
        finish,
        requestFocus: () => { focusRequested = true; },
        consumeFocusRequest: () => {
            const requested = focusRequested;
            focusRequested = false;
            return requested;
        }
    };
}
