import type {HawkiAppExtension, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly passkeySession: WithoutAppExtensionInternals<PasskeySessionExtension>;
    }
}

/**
 * Frontend-owned in-memory passkey session.
 *
 * The legacy UI may still populate this service through its compatibility
 * bridge, but routed features depend only on the application extension.
 */
export class PasskeySessionExtension implements HawkiAppExtension {
    private currentPasskey = $state<string | null>(null);

    /** The decrypted passkey for the current browser session. */
    public get passkey(): string | null {
        return this.currentPasskey;
    }

    public set passkey(value: string | null) {
        this.currentPasskey = value;
    }

    /** Removes the decrypted passkey from memory. */
    public clear(): void {
        this.currentPasskey = null;
    }

    public provideProperties(): Record<string, unknown> {
        return {passkeySession: this};
    }
}

/**
 * Shared instance registered in `app.ts`, exported so `legacy/OldUiBridge.svelte.ts`
 * can populate the same session the routed features read from.
 *
 * @todo remove this global export when the OldUiBridge dies
 */
export const passkeySessionExtension = new PasskeySessionExtension();
