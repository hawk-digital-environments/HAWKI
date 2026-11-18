import type {HawkiModule} from '$lib/kernel/modules/types.js';
import type {RouteRegistrar} from '$lib/components/ui/routing/index.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';
import BookOpenIcon from '$lib/components/ui/icons/iconset/BookOpenIcon.svelte';
import BotIcon from '$lib/components/ui/icons/iconset/BotIcon.svelte';
import CodeIcon from '$lib/components/ui/icons/iconset/CodeIcon.svelte';
import PencilEdit01Icon from '$lib/components/ui/icons/iconset/PencilEdit01Icon.svelte';

const loadPage = async () => import('./MockSpacePage.svelte');

/**
 * A placeholder space for developing the space switcher. Only registered in dev
 * builds (see `core.plugin.ts`), which is why its labels skip the translator:
 * they are fixtures, never shown to users.
 */
class MockSpaceModule implements HawkiModule {
    public constructor(
        readonly name: string,
        private readonly label: string,
        private readonly spaceIcon: IconComponent
    ) {}

    public routes(registrar: RouteRegistrar): void {
        registrar.lazyRoute('/', loadPage, {name: `${this.name}.index`});
    }

    public title(): string {
        return this.label;
    }

    public icon(): IconComponent {
        return this.spaceIcon;
    }
}

export const mockSpaces = (): HawkiModule[] => [
    new MockSpaceModule('mock-research', 'Research', BookOpenIcon),
    new MockSpaceModule('mock-writing', 'Writing', PencilEdit01Icon),
    new MockSpaceModule('mock-code', 'Code', CodeIcon),
    new MockSpaceModule('mock-assistants', 'Assistants', BotIcon)
];
