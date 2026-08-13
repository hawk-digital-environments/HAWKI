import type {HawkiApp, HawkiAppExtension} from '$lib/kernel/HawkiApp.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import {mount, unmount} from 'svelte';
import Shell from '$lib/app/components/Shell.svelte';
import {legacyInitializeSnippetApps} from '$lib/legacy/legacyInitializeSnippetApps.js';

/**
 * Declaration merging that exposes this extension's members directly on the
 * app object (`app.isBooting`, `app.mount()`, ...) — see {@link HawkiAppExtension}
 * / `createApp()` in `kernel/HawkiApp.ts`.
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        isMounted: boolean;
        isBooting: boolean;
        mountPoint: HTMLElement;

        mount(mountPointOrSelector?: HTMLElement | string): boolean;

        unmount(): Promise<void>;
    }
}

/**
 * App extension that mounts the SPA `Shell` component and bridges the boot
 * sequence to it.
 *
 * `ready()` mounts `Shell` into `#hawki-app` as soon as the DOM is ready —
 * before the rest of the app has finished bootstrapping — so a loading
 * indicator can show immediately (`Shell` renders `Loader` while
 * `isBooting` is `true`). `isBooting` only flips to `false` once the
 * bootstrapper's `finalization` stage has passed, at which point `Shell`
 * swaps to the real `RouterView`.
 *
 * Pages that don't have a `#hawki-app` element (still-legacy pages,
 * mid-migration) never get mounted this way; `ready()` falls back to
 * `legacyInitializeSnippetApps` for them instead.
 */
export class ShellExtension implements HawkiAppExtension {
    private readonly mountPointSelector = '#hawki-app';
    private _isBooting = $state<boolean>(true);
    private _mountPoint: HTMLElement | null = null;
    private _svelteAppInstance: any = null;
    private _app: HawkiApp | null = null;

    public ready(app: HawkiApp, bootstrapper: Bootstrapper): void | Promise<void> {
        this._app = app;

        function runWhenDomReady(callback: () => void) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    callback();
                });
            } else {
                callback();
            }
        }

        // Mount immediately (synchronously, no DOM-ready wait) so the loading shell shows up as early as
        // possible. Safe without an explicit check because app.ts is loaded as a `type="module"` script
        // (see @vite() in the Blade layouts), which the browser only ever executes after the document has
        // been parsed — `#hawki-app` is already in the DOM by the time `ready()` runs.
        let hasBeenMounted = false;
        hasBeenMounted = this.mount();

        // DOMContentLoaded may still be pending even though the DOM is parsed (e.g. deferred stylesheets/
        // other module scripts). Delay the 'finalization' stage's completion until it fires, since the
        // legacy fallback below and other finalization work may depend on it.
        bootstrapper.onFinalizationStage(() => new Promise(resolve => {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    resolve();
                });
            } else {
                resolve();
            }
        }));

        bootstrapper.onStagePassed('finalization', () => {
            this._isBooting = false;

            // @todo this is legacy behavior, that should be removed once we only have a single page app.
            if (!hasBeenMounted) {
                return legacyInitializeSnippetApps(app, runWhenDomReady);
            }
        });
    }

    public get isMounted(): boolean {
        return this._mountPoint !== null;
    }

    public get isBooting(): boolean {
        return this._isBooting;
    }

    public get mountPoint(): HTMLElement {
        if (!this._mountPoint) {
            throw new Error(`Mount point not found. Ensure that the element with selector '${this.mountPointSelector}' exists in the DOM.`);
        }
        return this._mountPoint;
    }

    private get app(): HawkiApp {
        if (!this._app) {
            throw new Error('HawkiApp instance is not set. Ensure that the ready() method has been called before accessing the app.');
        }
        return this._app;
    }

    /**
     * Mounts `Shell` into the given element/selector, or `#hawki-app` by
     * default. Returns `false` (without throwing) both when already mounted
     * and when the target element can't be found — `ready()` uses that to
     * decide whether to fall back to the legacy snippet bootstrap, so a
     * missing target is an expected outcome on legacy pages, not an error.
     */
    public mount(mountPointOrSelector?: HTMLElement | string): boolean {
        if (this.isMounted) {
            console.warn('SpaExtension is already mounted. Skipping mount.');
            return false;
        }

        let mountPoint: HTMLElement | null = null;
        if (mountPointOrSelector instanceof HTMLElement) {
            mountPoint = mountPointOrSelector;
        } else if (typeof mountPointOrSelector === 'string') {
            mountPoint = document.querySelector<HTMLElement>(mountPointOrSelector);
        } else {
            mountPoint = document.querySelector<HTMLElement>(this.mountPointSelector);
        }
        if (!mountPoint) {
            return false;
        }

        this._mountPoint = mountPoint;

        this._svelteAppInstance = mount(Shell, {
            target: mountPoint,
            props: {
                app: this.app
            }
        });

        return true;
    }

    public async unmount(): Promise<void> {
        if (this._svelteAppInstance) {
            await unmount(this._svelteAppInstance);
            this._svelteAppInstance = null;
        }

        if (this._mountPoint) {
            this._mountPoint.innerHTML = '';
            this._mountPoint = null;
        }
    }

    public provideProperties() {
        const extension = this;
        return {
            get isMounted() {
                return extension.isMounted;
            },
            get mountPoint() {
                return extension.mountPoint;
            },
            get isBooting() {
                return extension.isBooting;
            },
            mount(mountPointOrSelector?: HTMLElement | string) {
                return extension.mount(mountPointOrSelector);
            },
            unmount() {
                return extension.unmount();
            }
        };
    }
}
