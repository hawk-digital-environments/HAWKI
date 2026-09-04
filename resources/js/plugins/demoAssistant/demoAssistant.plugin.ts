import type {HawkiPlugin} from '$lib/kernel/plugins/types.js';
import type {HookRegistrar} from '$lib/kernel/hooks/hookRegistrar.js';
import type {AiAssistant} from '$plugins/core/stores/AiHandleStore.svelte.js';
import type {AssistantAppearance} from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance.js';

/** Which demo category an assistant is grouped under in the `@` menu. */
type DemoCategory = 'learning' | 'working';

/** One mocked assistant, in the shape the demo handler maps onto `AiAssistant`. */
interface DemoAssistant {
    id: string;
    handle: string;
    /** Translation keys reused from the chat namespace (they predate this plugin). */
    labelKey: string;
    descriptionKey: string;
    category: DemoCategory;
    /** Lifts the row into the menu's "Pinned" section — demonstrates the flag. */
    pinned?: boolean;
    appearance: AssistantAppearance;
}

/**
 * Mocked study assistants for the composer's `@` menu, standing in for the
 * real (server-provided) assistants: what the `AiHandleStore` used to hardcode
 * before its lists became hook-driven. Labels and descriptions resolve through
 * the translator; the color pairs are the OKLCH ramps the old mock table used.
 */
const DEMO_ASSISTANTS: DemoAssistant[] = [
    {
        id: 'tutor',
        handle: '@tutor',
        labelKey: 'chat.composer.assistantMenu.assistants.tutor',
        descriptionKey: 'chat.composer.assistantMenu.assistants.tutorDescription',
        category: 'learning',
        pinned: true,
        appearance: {
            icon: '🎓',
            colors: {from: 'oklch(0.703 0.137 93)', to: 'oklch(0.833 0.162 93)'}
        }
    },
    {
        id: 'exam',
        handle: '@exam',
        labelKey: 'chat.composer.assistantMenu.assistants.exam',
        descriptionKey: 'chat.composer.assistantMenu.assistants.examDescription',
        category: 'learning',
        appearance: {
            icon: '📚',
            colors: {from: 'oklch(0.537 0.188 33)', to: 'oklch(0.667 0.200 33)'}
        }
    },
    {
        id: 'research',
        handle: '@research',
        labelKey: 'chat.composer.assistantMenu.assistants.research',
        descriptionKey: 'chat.composer.assistantMenu.assistants.researchDescription',
        category: 'working',
        appearance: {
            icon: '🔬',
            colors: {from: 'oklch(0.672 0.112 213)', to: 'oklch(0.802 0.133 213)'}
        }
    },
    {
        id: 'writing',
        handle: '@writing',
        labelKey: 'chat.composer.assistantMenu.assistants.writing',
        descriptionKey: 'chat.composer.assistantMenu.assistants.writingDescription',
        category: 'working',
        appearance: {
            icon: '✍️',
            colors: {from: 'oklch(0.568 0.200 333)', to: 'oklch(0.698 0.200 333)'}
        }
    },
    {
        id: 'code',
        handle: '@code',
        labelKey: 'chat.composer.assistantMenu.assistants.code',
        descriptionKey: 'chat.composer.assistantMenu.assistants.codeDescription',
        category: 'working',
        appearance: {
            icon: '💻',
            colors: {from: 'oklch(0.703 0.172 153)', to: 'oklch(0.833 0.200 153)'}
        }
    }
];

/**
 * Reference plugin for extending the composer's assistant search via the hook
 * system — the whole extension is one `aiAssistants` handler.
 *
 * The hook receives the assistants collected so far (HAWKI's base row first)
 * and returns the list with the mocked ones appended; the `@` menu renders
 * them grouped by their `group` (see `AssistantMenu`), lifts `pinned` rows
 * into its "Pinned" section, and offers them to the caret mention popup and
 * the message chips like any other taggable assistant. The rows carry no
 * `onTogglePin`, so their pin buttons keep using the local `composer-pins`
 * store — compare with a server-backed plugin, which would route the toggle
 * to its API instead.
 *
 * Auto-discovered by the kernel's plugin glob; delete this folder to return
 * the `@` menu to HAWKI-only.
 */
export default class DemoAssistantPlugin implements HawkiPlugin {
    readonly name = 'demoAssistant';

    public hooks(registrar: HookRegistrar): void {
        registrar.add('aiAssistants', (assistants, ctx): AiAssistant[] => [
            ...assistants,
            ...DEMO_ASSISTANTS.map(demo => ({
                id: `demo:${demo.id}`,
                handle: demo.handle,
                label: ctx.translate(demo.labelKey),
                description: ctx.translate(demo.descriptionKey),
                group: {
                    id: `demo-category:${demo.category}`,
                    label: ctx.translate(`chat.composer.assistantMenu.demoCategory.${demo.category}`)
                },
                pinned: demo.pinned ?? false,
                appearance: demo.appearance
            }))
        ], {order: 10});
    }
}
