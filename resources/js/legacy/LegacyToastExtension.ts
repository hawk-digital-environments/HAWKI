import type {HawkiAppExtension, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {ToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        /**
         * @deprecated The toast context will only be provided via sveltes context feature once the rewrite to an SPA is complete.
         */
        readonly toast: WithoutAppExtensionInternals<LegacyToastExtension>;
    }
}

/**
 * @deprecated The toast context will only be provided via sveltes context feature once the rewrite to an SPA is complete.
 */
export class LegacyToastExtension implements HawkiAppExtension {
    private _context: ToastContext | null = null;

    public get context(): ToastContext {
        if (!this._context) {
            throw new Error('Toast context is not set. Make sure to set it before using it.');
        }
        return this._context;
    }

    public setContext(context: ToastContext): void {
        this._context = context;
    }

    public provideProperties(): Record<string, any> {
        const extension = this;
        return {
            get toast() {
                return extension;
            }
        };
    }
}
