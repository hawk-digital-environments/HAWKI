/**
 * Onboarding shown at the start of the registration, before the usage policy and
 * the keychain setup. Ported from the legacy registration wizard's welcome slides.
 *
 * Each step is a translation group under `ui.auth.register.welcome.<step>` with a
 * `title`, `body` and `action` (the label of the button that moves on).
 */
export type WelcomeStep = 'encryption' | 'groups' | 'passkey' | 'automaticPasskey';

/** The steps in order; the last one explains how the passkey comes about in the configured mode. */
export function welcomeSteps(passkeyAutoGenerate: boolean): WelcomeStep[] {
    return ['encryption', 'groups', passkeyAutoGenerate ? 'automaticPasskey' : 'passkey'];
}
