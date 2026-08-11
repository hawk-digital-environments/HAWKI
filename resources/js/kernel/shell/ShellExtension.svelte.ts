import type {HawkiApp, HawkiAppExtension} from '$lib/kernel/HawkiApp.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import {mount, unmount} from 'svelte';
import Shell from '$lib/app/components/Shell.svelte';
import {legacyInitializeSnippetApps} from '$lib/legacy/legacyInitializeSnippetApps.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        isMounted: boolean;
        isBooting: boolean;
        mountPoint: HTMLElement;

        mount(mountPointOrSelector?: HTMLElement | string): boolean;

        unmount(): Promise<void>;
    }
}

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

        // We mount emmediatey as soon as the DOM is ready, so we can show a fancy loading screen while the rest of the app is booting.
        // This only renders the shell and a loading indicator, the rest of the app will be rendered once "_isBooting" is set to false.
        let hasBeenMounted = false;
        hasBeenMounted = this.mount();

        // Ensure the dom is ready before we mount.
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
