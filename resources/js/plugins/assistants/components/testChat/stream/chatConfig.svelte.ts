import { getContext, setContext } from "svelte";
import type { Assistant } from "$plugins/assistants/types/assistant";

/**
 * The assistant under test. Callers hand in an accessor (not a snapshot) so
 * the test chat always reflects the current value — the builder passes its
 * live `draft` so unsaved edits stream immediately, the store's detail page
 * passes its fetched assistant.
 *
 * Saved assistants (a non-null `handle`) stream through the assistant
 * route: the payload carries `hawkiExtensions.assistant_handle`, and the
 * backend composes the run from the assistant row (system prompt module
 * stack, ambient knowledge tool, capabilities, model selection policy).
 * Only never-saved drafts fall back to the legacy raw-fields payload —
 * and knowledge files cannot exist before the first save anyway, since
 * attachments need an assistant row.
 */
/**
 * What the chat talks to: `test` chats with the assistant under test, `guide`
 * with the builder's setup guide about that assistant.
 */
export type ChatVariant = "test" | "guide";

/** Builder fields the test chat's empty state can point to as required. */
export type RequiredFieldKey = "systemPrompt" | "handle";

/** Translation keys for the required-field chips (same labels the guide fills). */
export const REQUIRED_FIELD_LABELS: Record<RequiredFieldKey, string> = {
    handle: "assistants.builder.general.input_handle",
    systemPrompt: "assistants.builder.behaviour.input_system_prompt",
};

export type ChatConfigApi = {
    readonly assistant: Assistant;
    readonly hasModel: boolean;
    readonly variant: ChatVariant;
    /**
     * The builder fields still missing before the test chat has anything to
     * test: triggered when the system prompt AND the tool list are both
     * empty. Lists `systemPrompt` and — only while the assistant has no
     * handle yet (never saved) — `handle`. Empty once either is filled.
     */
    readonly missingRequired: readonly RequiredFieldKey[];
    /**
     * Opens the builder step showing a required field and scrolls to it.
     * Absent outside the builder (the dashboard detail page), where the
     * chips render without a jump target.
     */
    readonly openField: ((key: RequiredFieldKey) => void) | undefined;
};

export type ChatConfigOptions = {
    readonly openField?: (key: RequiredFieldKey) => void;
};

const KEY = Symbol("chat-config");

const deriveMissingRequired = (assistant: Assistant): RequiredFieldKey[] => {
    if (assistant.systemPrompt.trim() !== "" || (assistant.aiTools?.length ?? 0) > 0) {
        return [];
    }

    const missing: RequiredFieldKey[] = ["systemPrompt"];
    if (!assistant.handle) {
        missing.push("handle");
    }
    return missing;
};

/** A config without context, for chat stores owned outside a `<Chatbox>`. */
export const createChatConfig = (
    getAssistant: () => Assistant,
    variant: ChatVariant = "test",
    options: ChatConfigOptions = {},
): ChatConfigApi => ({
    get assistant(): Assistant {
        return getAssistant();
    },
    get hasModel(): boolean {
        return getAssistant().model.length > 0;
    },
    variant,
    get missingRequired(): readonly RequiredFieldKey[] {
        return variant === "test" ? deriveMissingRequired(getAssistant()) : [];
    },
    openField: options.openField,
});

export const provideChatConfig = (
    getAssistant: () => Assistant,
    variant: ChatVariant = "test",
    options: ChatConfigOptions = {},
): ChatConfigApi => {
    const api = createChatConfig(getAssistant, variant, options);

    setContext(KEY, api);
    return api;
};

export const useChatConfig = (): ChatConfigApi => {
    const ctx = getContext<ChatConfigApi>(KEY);
    if (!ctx) throw new Error("useChatConfig() must be used within a <Chatbox> provider");
    return ctx;
};
