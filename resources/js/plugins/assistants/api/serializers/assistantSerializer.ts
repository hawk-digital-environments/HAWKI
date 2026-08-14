import { BACKGROUNDS } from "$plugins/assistants/presets/backgrounds";
import { ReleaseMode, type Assistant, type AssistantKey } from "$plugins/assistants/types/assistant";

/**
 * The **write** half of the assistant wire mapping: object → JSON:API request
 * body, plus the blank assistant the builder starts from.
 *
 * The read half (`assistantFromApi`) is gone. It now lives in
 * `api/schemas/assistants.schema.ts` as a Zod `.transform()`, so the same
 * declaration that *validates* a response also *maps* it — see that file's
 * header for the wire→domain table. Anything that used `assistantFromApi`
 * should call `getAssistant()` / `listAssistants()` instead; they return
 * mapped `Assistant` objects directly.
 *
 * Writing stays hand-written on purpose:
 *  - `assistantToApi` emits a *partial* body driven by `changedKeys`, so the
 *    builder can PATCH only what the user touched. A schema describes one
 *    fixed shape and cannot express that.
 *  - Relationships are written as JSON:API resource identifiers
 *    (`{type, id}`), which is a different shape from the inlined objects that
 *    come back on read — so this is not simply the inverse of the read schema.
 */

const TYPE = "assistants";

/**
 * A blank assistant: the builder's initial state before the server has minted
 * a record.
 *
 * Empty strings rather than `null` for the text fields, because that is what
 * `AssistantSchema` declares — the return type is checked against it, no cast.
 * (The old version cast a null-filled object with `as Assistant`, which also
 * quietly broke the builder's `description !== ''` completeness rule: `null`
 * is not `''`, so a blank description reported as filled.)
 */
export function createEmptyAssistant(): Assistant {
  return {
    id: null,
    name: "",
    handle: null,
    systemPrompt: "",
    greeting: "",
    starterPrompts: [],
    description: "",
    detailDescription: "",

    model: "",
    allowModelSelect: false,
    maxTokens: 2048,
    temp: 0,
    topP: 0,
    allowRemix: false,
    releaseStage: ReleaseMode.DRAFT,
    requested_release_stage: null,
    riskLevel: null,
    riskNote: null,
    usageCount: null,
    isFavorite: false,
    createdAt: "",
    updatedAt: "",

    formality: null,
    answerLength: null,
    language: null,

    category: null,
    avatar: {
      name: "📚",
      iconCss: BACKGROUNDS[0].value
    },
    tags: [],
    creator: { id: "", displayName: "" },
    versions: [],
    files: [],
    knowledgeBases: [],
    submissionNote: "",

    actionPermissions: null,

    remixCreator: null,
    remixedAssistant: null,
  };
}


/** A JSON:API resource object as sent in a POST/PATCH request body. */
export interface JsonApiResourceBody {
  type: string;
  id?: string | null;
  attributes?: Record<string, unknown>;
  relationships?: Record<string, unknown>;
}

export function assistantToApi(
  assistant: Assistant,
  changedKeys?: Set<AssistantKey>,
): JsonApiResourceBody {
  const include = (key: AssistantKey) =>
    !changedKeys || changedKeys.has(key);
  return {
    type: TYPE,
    id: assistant.id,
    attributes: {
      ...(include("name") && { name: assistant.name }),
      ...(include("handle") && { handle: assistant.handle }),
      ...(include("systemPrompt") && { system_prompt: assistant.systemPrompt }),
      ...(include("greeting") && { greeting: assistant.greeting }),
      ...(include("description") && { description: assistant.description }),
      ...(include("detailDescription") && {
        detail_description: assistant.detailDescription,
      }),
      ...(include("allowRemix") && { allow_remix: assistant.allowRemix }),
      ...(include("allowModelSelect") && {
        allow_model_select: assistant.allowModelSelect,
      }),
      ...(include("releaseStage") && { release_stage: assistant.releaseStage }),
      ...(include("model") && { model: assistant.model }),
      ...(include("maxTokens") && { max_tokens: assistant.maxTokens }),
      ...(include("temp") && { temp: assistant.temp }),
      ...(include("topP") && { top_p: assistant.topP }),
      ...(include("createdAt") && { created_at: assistant.createdAt }),
      ...(include("updatedAt") && { updated_at: assistant.updatedAt }),
      ...(include("isFavorite") && { is_favorite: assistant.isFavorite }),
    },
    relationships: {
      ...(include("category") &&
        assistant.category && {
          assistant_category: {
            data: {
              type: "assistant-categories",
              id: assistant.category.id,
            },
          },
        }),

      ...(include("tags") &&
        assistant.tags && {
          assistant_tags: {
            data: assistant.tags.map((t) => ({
              type: "assistant-tags",
              id: t.id,
            })),
          },
        }),

      ...(include("aiTools") &&
        assistant.aiTools && {
          ai_tools: {
            data: assistant.aiTools.map((tool) => ({
              type: "ai-tools",
              id: String(tool.id),
            })),
          },
        }),
    },
  };
}
