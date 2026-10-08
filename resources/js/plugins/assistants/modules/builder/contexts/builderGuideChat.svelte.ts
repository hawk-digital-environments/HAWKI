import type { BuilderContext } from "./BuilderContext.svelte.js";
import { valuesEqual } from "./builderUtils.js";
import type { KnowledgeUploader } from "$plugins/assistants/modules/builder/components/fileUpload/knowledgeUploader.svelte.js";
import {
    BUILDER_GUIDE_FIELDS,
    BUILDER_GUIDE_SETTING_FIELDS,
    requestBuilderGuide,
    type BuilderGuideField,
    type BuilderGuideSettingField,
    type BuilderGuideMessage,
    type BuilderGuideUpdates,
} from "$plugins/assistants/api/resources/assistantBuilderGuideClient";
import type { ChatStatus, ChatStoreApi } from "$plugins/assistants/components/testChat/stream/chatStore.svelte.js";
import type { AppliedField, ChatMessage, IngestFile } from "$plugins/assistants/components/testChat/types";
import type { AssistantTag } from "$plugins/assistants/types/assistant/AssistantTag";
import { BUILDER_STEPS, type BuilderStep } from "./builderValidationRules.js";
import type { useRouter } from "$lib/components/ui/routing/index.js";
import { assistantOptionsStore } from "$plugins/assistants/stores/AssistantOptionsStore.svelte";
import { BACKGROUNDS } from "$plugins/assistants/presets/backgrounds";

/** Builder step each field the guide can fill lives on. */
const FIELD_STEPS: Record<keyof typeof FIELD_LABELS, BuilderStep> = {
    name: "general",
    handle: "general",
    description: "general",
    detailDescription: "general",
    category: "general",
    language: "general",
    tags: "general",
    avatar: "general",
    model: "model",
    systemPrompt: "behaviour",
    greeting: "behaviour",
    starterPrompts: "behaviour",
    formality: "behaviour",
    answerStyle: "behaviour",
};

/** Builder label of every field the guide can fill. */
const FIELD_LABELS: Record<BuilderGuideField | BuilderGuideSettingField | "handle" | "category" | "model" | "tags" | "avatar", string> = {
    handle: "assistants.builder.general.input_handle",
    category: "assistants.builder.general.input_category",
    model: "assistants.builder.model.input_model",
    name: "assistants.builder.general.input_name",
    description: "assistants.builder.general.input_description",
    detailDescription: "assistants.builder.general.input_short_description",
    systemPrompt: "assistants.builder.behaviour.input_system_prompt",
    greeting: "assistants.builder.behaviour.input_greeting",
    starterPrompts: "assistants.builder.behaviour.input_starter_prompts",
    language: "assistants.settings.language.label",
    formality: "assistants.settings.formality.label",
    answerStyle: "assistants.settings.answer_style.label",
    tags: "assistants.builder.general.input_tags",
    avatar: "assistants.builder.general.avatar_title",
};

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
 * Files picked or dropped on the chat go into the assistant's knowledge right
 * away through the shared {@link KnowledgeUploader}. Each batch shows up in
 * the conversation as an `ingest` part with its upload progress, and once it
 * has landed the guide takes a turn on its own to react to the files (the
 * server reads their content itself).
 *
 * Implements the test chat's {@link ChatStoreApi}, so the same `<Chatbox>`
 * renders it. Owned by the test panel, so it survives mode and step changes.
 */
export function createBuilderGuideChat(
    builder: BuilderContext,
    uploader: KnowledgeUploader,
    __: (key: string) => string,
    router: Pick<ReturnType<typeof useRouter>, "goToRoute" | "isRouteActive">,
): ChatStoreApi {
    let messages = $state<ChatMessage[]>([]);
    let status = $state<ChatStatus>("idle");
    let error = $state<string | null>(null);
    let uploading = $state(false);
    /** Files landed while a turn was running; the guide reacts once it is done. */
    let filesPending = false;
    let abortCtrl: AbortController | null = null;

    /** What the guide reads for a message: its text, and a note about the files that landed. */
    const toGuideContent = (m: ChatMessage): string => m.parts
        .map((p) => {
            if (p.type === "text") return p.text;
            if (p.type !== "ingest") return "";
            const names = p.files.filter((f) => f.state === "done").map((f) => f.name);
            return names.length ? `[Uploaded knowledge files: ${names.join(", ")}]` : "";
        })
        .filter(Boolean)
        .join("\n\n");

    /**
     * The conversation as the guide reads it. An upload and a message the
     * creator wrote meanwhile are both theirs, so consecutive turns of one
     * role merge into one.
     */
    const toGuideHistory = (): BuilderGuideMessage[] => {
        const out: BuilderGuideMessage[] = [];
        for (const m of messages) {
            const content = toGuideContent(m);
            if (!content) continue;
            const last = out[out.length - 1];
            if (last?.role === m.role) last.content += `\n\n${content}`;
            else out.push({ role: m.role, content });
        }
        return out;
    };

    /**
     * Open the step a field lives on and scroll to the field. The scroll is
     * requested before navigating (the field picks it up once its page has
     * mounted; `isRouteActive` can still report the old step right after
     * `goToRoute` resolves), and only when the step lock will let the
     * navigation through, so a vetoed one leaves no request pending.
     */
    const openField = async (key: keyof typeof FIELD_LABELS): Promise<void> => {
        const step = FIELD_STEPS[key];
        const route = `assistants.builder.${step}`;
        if (BUILDER_STEPS.indexOf(step) <= builder.validator.firstIncompleteStep) {
            builder.requestScrollTo(key);
        }
        if (!router.isRouteActive(route)) await router.goToRoute(route);
    };

    /**
     * The tags for the guide's tag names: the draft's own and existing ones
     * are reused, new ones are created (`addTag`). A tag that can't be
     * created is left out rather than failing the whole turn.
     */
    const resolveTags = async (names: string[]): Promise<AssistantTag[]> => {
        const results = await Promise.allSettled(names.map((name) =>
            builder.draft.tags.find((tag) => tag.text === name) ?? assistantOptionsStore.addTag(name)
        ));
        return results.flatMap((r) => r.status === "fulfilled" ? [r.value] : []);
    };

    /**
     * Write the guide's field values into the draft; returns the fields that
     * changed. The server has already checked handle, category, model and
     * the settings against what the creator may pick.
     */
    const applyUpdates = async (updates: BuilderGuideUpdates): Promise<AppliedField[]> => {
        // Tags first: creating new ones is the only step that can take a while.
        const tags = updates.tags !== undefined ? await resolveTags(updates.tags) : undefined;
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
        for (const key of BUILDER_GUIDE_SETTING_FIELDS) {
            const value = updates[key];
            if (value === undefined || value === builder.draft[key]) continue;
            builder.set(key, value);
            filled.push(key);
        }
        if (tags !== undefined && !valuesEqual(tags.map((t) => t.id), builder.draft.tags.map((t) => t.id))) {
            builder.set("tags", tags);
            filled.push("tags");
        }
        const background = BACKGROUNDS.find((bg) => bg.id === updates.avatar?.background);
        const avatar = {
            ...builder.draft.avatar,
            name: updates.avatar?.emoji || builder.draft.avatar.name,
            iconCss: background?.value ?? builder.draft.avatar.iconCss,
        };
        if (avatar.name !== builder.draft.avatar.name || avatar.iconCss !== builder.draft.avatar.iconCss) {
            builder.set("avatar", avatar);
            filled.push("avatar");
        }
        if (updates.model !== undefined && updates.model !== builder.draft.model) {
            builder.setModel(updates.model);
            filled.push("model");
        }
        // Highlights the filled fields in the builder (see AiFillReveal.svelte).
        builder.markAiFilled(filled);
        // Listed in builder order, each linking to the step it lives on.
        return filled
            .map((key, i) => ({ key, i }))
            .sort((a, b) => BUILDER_STEPS.indexOf(FIELD_STEPS[a.key]) - BUILDER_STEPS.indexOf(FIELD_STEPS[b.key]) || a.i - b.i)
            .map(({ key }) => ({ label: __(FIELD_LABELS[key]), open: () => void openField(key) }));
    };

    const clear = (): void => {
        abortCtrl?.abort();
        filesPending = false;
        messages = [];
        error = null;
        status = "idle";
    };

    /**
     * One guide turn over the conversation so far. `trigger` marks a turn the
     * guide takes on its own. Files that land meanwhile get a turn of their
     * own right after.
     */
    const respond = async (trigger?: "files"): Promise<void> => {
        // This turn's history already covers every file that has landed.
        filesPending = false;
        const history = toGuideHistory();

        messages.push({ id: crypto.randomUUID(), role: "assistant", parts: [], streaming: true, ...(trigger ? { trigger } : {}) });
        const idx = messages.length - 1;

        status = "streaming";
        error = null;
        const ctrl = new AbortController();
        abortCtrl = ctrl;

        try {
            const { reply, updates } = await requestBuilderGuide(builder.draft, history, ctrl.signal);
            if (ctrl.signal.aborted) return;
            const fields = await applyUpdates(updates);
            if (ctrl.signal.aborted) return;
            messages[idx].parts = [
                ...(reply ? [{ type: "text" as const, text: reply }] : []),
                ...(fields.length ? [{ type: "applied" as const, fields }] : []),
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

        if (filesPending) void respond("files");
    };

    const send = async (text: string): Promise<void> => {
        const content = text.trim();
        if (!content || status === "streaming") return;

        messages.push({ id: crypto.randomUUID(), role: "user", parts: [{ type: "text", text: content }] });
        await respond();
    };

    /**
     * Upload files into the knowledge and show them in the conversation while
     * they do; once at least one has landed, the guide reacts to them.
     */
    const add = async (files: File[]): Promise<void> => {
        if (uploading || files.length === 0) return;
        uploading = true;

        messages.push({
            id: crypto.randomUUID(),
            role: "user",
            parts: [{
                type: "ingest",
                files: files.map((f): IngestFile => ({ name: f.name, size: f.size, progress: 0, state: "uploading" })),
            }],
        });
        // Through the state proxy, so progress updates render.
        const message = messages[messages.length - 1];
        const part = message.parts[0] as Extract<ChatMessage["parts"][number], { type: "ingest" }>;
        const entryOf = (file: File): IngestFile | undefined => part.files[files.indexOf(file)];

        try {
            const uploaded = await uploader.addFiles(files, (file, progress) => {
                const entry = entryOf(file);
                if (entry) entry.progress = progress;
            });
            part.files.forEach((entry, i) => {
                const done = uploaded.some((u) => u.file === files[i]);
                entry.state = done ? "done" : "failed";
                if (done) entry.progress = 100;
            });
        } catch {
            part.files.forEach((entry) => entry.state = "failed");
        } finally {
            uploading = false;
        }

        // Cleared meanwhile, or nothing landed: nothing to react to.
        if (!messages.includes(message) || !part.files.some((f) => f.state === "done")) return;
        filesPending = true;
        if (status !== "streaming") void respond("files");
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
            get busy(): boolean {
                return uploading;
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
        },
        clear,
        send,
    };
}
