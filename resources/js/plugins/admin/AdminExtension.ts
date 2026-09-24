import type { HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals } from '$lib/kernel/HawkiApp.js';
import type { RegisteredAdminSection, RegisteredAdminWorkspace } from './api.js';
import type { AdminRegistry } from './registry.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        admin: WithoutAppExtensionInternals<AdminExtension>;
    }
}

/**
 * Publishes the Admin Registry after every Module exists. `createApp` initializes
 * plugin-registered extensions after the entry-point Module and Routing
 * extensions and before `bootstrapper.run()`. Route group callbacks run only
 * when the router is built at the late stage, so `routes.ts` reads the complete
 * registry when it registers Workspace routes.
 */
export class AdminExtension implements HawkiAppExtension {
    public constructor(private readonly registry: AdminRegistry) {}

    public get sections(): RegisteredAdminSection[] {
        return this.registry.sections;
    }

    public get workspaces(): RegisteredAdminWorkspace[] {
        return this.registry.workspaces;
    }

    public workspace(idOrName: string): RegisteredAdminWorkspace | undefined {
        return this.registry.workspace(idOrName);
    }

    public init(app: UnfinishedHawkiApp): void {
        this.registry.collect(app.getOrFail('modules').all);
    }

    public provideProperties() {
        const extension = this;
        return {
            get admin() {
                return extension;
            }
        };
    }
}
