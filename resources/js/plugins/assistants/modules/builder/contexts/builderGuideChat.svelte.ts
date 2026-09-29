import type { BuilderContext } from "./BuilderContext.svelte.js";
import { valuesEqual } from "./builderUtils.js";
import type { KnowledgeUploader } from "$plugins/assistants/modules/builder/components/fileUpload/knowledgeUploader.svelte.js";
import {
    BUILDER_GUIDE_FIELDS,
    requestBuilderGuide,
    type BuilderGuideField,
    type BuilderGuideMessage,
    type BuilderGuideUpdates,
} from "$plugins/assistants/api/resources/assistantBuilderGuideClient";
import type {
    ChatStatus,
    ChatStoreApi,
    StagedUpload,
} from "$plugins/assistants/components/testChat/stream/chatStore.svelte.js";
import type { ChatMessage } from "$plugins/assistants/components/testChat/types";
import type { UploadFile } from "$plugins/assistants/types/UploadFile";
import { assistantOptionsStore } from "$plugins/assistants/stores/AssistantOptionsStore.svelte";

/** Builder label of every field the guide can fill. */
const FIELD_LABELS: Record<BuilderGuideField | "handle" | "category" | "model", string> = {
    handle: "assistants.builder.general.input_handle",
    category: "assistants.builder.general.input_category",
    model: "assistants.builder.model.input_model",
    name: "assistants.builder.general.input_name",
    description: "assistants.builder.general.input_description",
    detailDescription: "assistants.builder.general.input_short_description",
    systemPrompt: "assistants.builder.behaviour.input_system_prompt",
    greeting: "assistants.builder.behaviour.input_greeting",
    starterPrompts: "assistants.builder.behaviour.input_starter_prompts",
};

type Staged = StagedUpload & { file?: UploadFile };

/**
 * # Builder guide chat
 *
 * The builder test panel's second conversation: instead of talking to the
 * assistant under test, the creator talks to a setup guide (an LLM behind
 * `assistants/{id}/actions/builder-guide`) that asks what the assistant should
 * do, answers questions and fills builder fields through structured output.
 * Returned field values are written straight into the draft via
 * `builder.set()`, so they autosave like any manual edit and show up on the
 * builder pages immediately.
 *
 * Files picked in the composer go into the assistant's knowledge through the
 * shared {@link KnowledgeUploader} and are staged until the next message, which
 * tells the guide about them (the server reads their content itself).
 *
 * Implements the test chat's {@link ChatStoreApi}, so the same `<Chatbox>`
 * renders it. Owned by the test panel, so it survives mode and step changes.
 */
export function createBuilderGuideChat(
    builder: BuilderContext,
    uploader: KnowledgeUploader,
    __: (key: string) => string,
): ChatStoreApi {
    let messages = $state<ChatMessage[]>([]);
    let status = $state<ChatStatus>("idle");
    let error = $state<string | null>(null);
    let staged = $state<Staged[]>([]);
    let abortCtrl: AbortController | null = null;

    /** What the guide reads for a message: its text plus a note about files uploaded with it. */
    const toGuideMessage = (m: ChatMessage): BuilderGuideMessage => {
        const text = m.parts
            .filter((p): p is Extract<typeof p, { type: "text" }> => p.type === "text")
            .map((p) => p.text)
            .join("");
        const files = m.attachments?.length ? `[Uploaded knowledge files: ${m.attachments.join(", ")}]` : "";
        return { role: m.role, content: [text, files].filter(Boolean).join("\n\n") };
    };

    /**
     * Write the guide's field values into the draft; returns the labels of
     * the fields that changed. The server has already checked handle,
     * category and model against what the creator may pick.
     */
    const applyUpdates = (updates: BuilderGuideUpdates): string[] => {
        const filled: (keyof typeof FIELD_LABELS)[] = [];
        for (const key of BUILDER_GUIDE_FIELDS) {
            const value = updates[key];
            if (value === undefined || valuesEqual(value, builder.draft[key])) continue;
            builder.set(key, value as never);
            filled.push(key);
        }
        if (updates.handle !== undefined && updates.handle !== builder.draft.handle) {
            builder.set("handle", updates.handle);
            filled.push("handle");
        }
        const category = assistantOptionsStore.categories.find((c) => c.id === updates.categoryId);
        if (category && category.id !== builder.draft.category?.id) {
            builder.set("category", {...category});
            filled.push("category");
        }
        if (updates.model !== undefined && updates.model !== builder.draft.model) {
            builder.setModel(updates.model);
            filled.push("model");
        }
        // Highlights the filled fields in the builder (see AiFillReveal.svelte).
        builder.markAiFilled(filled);
        return filled.map((key) => __(FIELD_LABELS[key]));
    };

    const clear = (): void => {
        abortCtrl?.abort();
        messages = [];
        error = null;
        status = "idle";
    };

    const send = async (text: string): Promise<void> => {
        const content = text.trim();
        const attachments = staged.filter((s) => !s.uploading).map((s) => s.name);
        if ((!content && attachments.length === 0) || status === "streaming") return;
        if (staged.some((s) => s.uploading)) return;

        staged = [];
        messages.push({
            id: crypto.randomUUID(),
            role: "user",
            parts: content ? [{ type: "text", text: content }] : [],
            ...(attachments.length ? { attachments } : {}),
        });
        const history = messages.map(toGuideMessage);

        messages.push({ id: crypto.randomUUID(), role: "assistant", parts: [], streaming: true });
        const idx = messages.length - 1;

        status = "streaming";
        error = null;
        const ctrl = new AbortController();
        abortCtrl = ctrl;

        try {
            const { reply, updates } = await requestBuilderGuide(builder.draft, history, ctrl.signal);
            if (ctrl.signal.aborted) return;
            const labels = applyUpdates(updates);
            messages[idx].parts = [
                ...(reply ? [{ type: "text" as const, text: reply }] : []),
                ...(labels.length ? [{ type: "applied" as const, labels }] : []),
            ];
            messages[idx].streaming = false;
            status = "idle";
        } catch {
            if (ctrl.signal.aborted) return;
            // Drop the empty placeholder; the error line takes its place.
            messages.splice(idx, 1);
            status = "error";
            error = __("assistants.builder.guide.error");
        } finally {
            if (abortCtrl === ctrl) abortCtrl = null;
        }
    };

    const add = async (files: File[]): Promise<void> => {
        const placeholders: Staged[] = files.map((f) => ({ name: f.name, uploading: true }));
        staged.push(...placeholders);
        const uploaded = await uploader.addFiles(files);
        staged = [
            ...staged.filter((s) => !placeholders.some((p) => p.name === s.name && s.uploading)),
            ...uploaded.map((file) => ({ name: file.name, uploading: false, file })),
        ];
    };

    const remove = async (upload: StagedUpload): Promise<void> => {
        const entry = staged.find((s) => s.name === upload.name && !s.uploading);
        if (!entry) return;
        if (entry.file && !(await uploader.removeFile(entry.file))) return;
        staged = staged.filter((s) => s !== entry);
    };

    return {
        get messages(): ChatMessage[] {
            return messages;
        },
        get status(): ChatStatus {
            return status;
        },
        get error(): string | null {
            return error;
        },
        get ready(): boolean {
            return builder.draft.id !== null;
        },
        uploads: {
            get staged(): StagedUpload[] {
                return staged;
            },
            get accept(): string | undefined {
                return uploader.acceptFilter;
            },
            get blockedHint(): string | null {
                switch (uploader.blockedReason) {
                    case "no-model":
                        return __("assistants.builder.guide.upload_needs_model");
                    case "no-knowledge-tool":
                        return __("assistants.builder.knowledge.upload_disabled_model_not_configured");
                    default:
                        return null;
                }
            },
            add,
            remove,
        },
        clear,
        send,
    };
}
