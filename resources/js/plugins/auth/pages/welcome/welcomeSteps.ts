/**
 * Onboarding shown at the start of the registration, before the usage policy and
 * the keychain setup. Ported from the legacy registration wizard's welcome slides.
 *
 * An administrator decides which steps come first (`branding.onboarding`): built-in
 * steps, whose texts are the translation groups `ui.auth.register.welcome.<step>`
 * (`title`, `body`, `action`), and steps of their own. The passkey step always ends it.
 */
export type BuiltinWelcomeStep = 'encryption' | 'groups' | 'passkey' | 'automaticPasskey';

/** A step an administrator wrote; shown as entered, in one language. */
export interface CustomWelcomeStep {
    emoji: string | null;
    title: string;
    text: string;
    /** Label of the button that moves on; null for the default label. */
    action: string | null;
}

/** Texts an administrator gave a built-in step; each one replaces the translated default it stands for. */
export interface WelcomeStepOverrides {
    emoji?: string | null;
    title?: string | null;
    text?: string | null;
    action?: string | null;
}

/** One entry of the configured onboarding, as the public configuration lists it. */
export type OnboardingEntry =
    | ({type: 'builtin'; step: 'encryption' | 'groups'} & WelcomeStepOverrides)
    | ({type: 'custom'} & CustomWelcomeStep);

/** A step as the onboarding routes it: a route segment and either a built-in step or a custom one. */
export interface WelcomeStepDefinition {
    id: string;
    builtin?: BuiltinWelcomeStep;
    /** The administrator's texts for a built-in step. */
    overrides?: WelcomeStepOverrides;
    custom?: CustomWelcomeStep;
}

/** The onboarding before an administrator changes it; `config/hawki.php` holds the same default. */
export const DEFAULT_ONBOARDING: OnboardingEntry[] = [
    {type: 'builtin', step: 'encryption'},
    {type: 'builtin', step: 'groups'}
];

/** The steps in order; the last one explains how the passkey comes about in the configured mode. */
export function welcomeSteps(passkeyAutoGenerate: boolean, onboarding: readonly OnboardingEntry[] = DEFAULT_ONBOARDING): WelcomeStepDefinition[] {
    const intro = onboarding.map((entry, index): WelcomeStepDefinition => {
        if (entry.type === 'builtin') {
            const {type: _type, step, ...overrides} = entry;
            return {id: step, builtin: step, overrides};
        }
        const {type: _type, ...custom} = entry;
        return {id: `step-${index + 1}`, custom};
    });
    const passkey = passkeyAutoGenerate ? 'automaticPasskey' : 'passkey';
    return [...intro, {id: passkey, builtin: passkey}];
}

/** Emoji shown in a tinted circle above each step's heading. Decorative, so not translated. */
export const welcomeStepEmoji: Record<BuiltinWelcomeStep, string> = {
    encryption: '🔒',
    groups: '🙌',
    passkey: '🔑',
    automaticPasskey: '✨'
};

/** Emoji of a custom step that has none of its own. */
export const DEFAULT_STEP_EMOJI = '💬';
