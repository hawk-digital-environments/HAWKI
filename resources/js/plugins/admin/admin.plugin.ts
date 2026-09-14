import type { HawkiPlugin } from '$lib/kernel/plugins/types.js';
import type { ModuleRegistrar } from '$lib/kernel/modules/moduleRegistrar.js';
import type { ResourceSchemaRegistrar } from '$lib/kernel/resources/resourceSchemaRegistrar.js';
import { AdminModule } from './AdminModule.js';
import { sections } from './sections.js';
import { AdminRowSchema } from './schemas/admin-content.js';

export default class AdminPlugin implements HawkiPlugin {
    readonly name = 'admin';

    modules({ add }: ModuleRegistrar) {
        add(new AdminModule());
    }

    resourceSchemas(registrar: ResourceSchemaRegistrar) {
        for (const section of sections) registrar.add(`admin-${section.id}`, AdminRowSchema);
    }
}
