import type { AppExtensionRegistrar, HawkiPlugin } from '$lib/kernel/plugins/types.js';
import type { ModuleRegistrar } from '$lib/kernel/modules/moduleRegistrar.js';
import type { ResourceSchemaRegistrar } from '$lib/kernel/resources/resourceSchemaRegistrar.js';
import { AdminExtension } from './AdminExtension.js';
import { AdminModule } from './AdminModule.js';
import { AdminRegistry } from './registry.js';

export default class AdminPlugin implements HawkiPlugin {
    readonly name = 'admin';
    private readonly registry = new AdminRegistry();

    extensions({ addExtension }: AppExtensionRegistrar) {
        addExtension(new AdminExtension(this.registry));
    }

    modules({ add }: ModuleRegistrar) {
        add(new AdminModule(this.registry));
    }

    resourceSchemas(registrar: ResourceSchemaRegistrar) {
        registrar.addFromModules(import.meta.glob('$lib/plugins/admin/schemas/resources/*.schema.ts', { eager: true }));
    }
}
