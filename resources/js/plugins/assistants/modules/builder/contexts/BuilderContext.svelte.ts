/**
 * # Builder Context
 *
 * One assistant-builder session — the draft being edited, its autosave
 * pipeline, and the {@link BuilderValidatorContext} that judges it — scoped to
 * the component subtree that owns the build/edit flow.
 *
 * ## Why a context and not a module-level store
 *
 * The previous `assistantBuilderStore` (and its `validator`) were singletons
 * created at import time: allocated as soon as the module was pulled into the
 * bundle, alive for the whole session even outside the builder route, with no
 * owner to release them once the user leaves. A context instance is owned by
 * the component that calls {@link createBuilderContext} — created on mount,
 * garbage-collected on unmount — and can't be shared by two unrelated builder
 * sessions. Same rationale as `AssistantListContext`
 * (`modules/dashboard/contexts/AssistantListContext.svelte.ts`).
 *
 * ## Usage
 *
 * ```svelte
 * <!-- modules/builder/pages/advance/builderLayout.svelte — the owner -->
 * <script lang="ts">
 *     import { createBuilderContext } from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
 *     import { useToastContext } from '$lib/components/ui/toast/ToastContext.svelte.js';
 *     import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
 *
 *     const { __ } = useTranslator();
 *     const builder = createBuilderContext(useToastContext(), __);
 *     onMount(() => builder.init());
 * </script>
 * ```
 *
 * ```svelte
 * <!-- any descendant — no props to thread through -->
 * <script lang="ts">
 *     import { useBuilderContext } from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
 *     const builder = useBuilderContext();
 *     const nameError = $derived(builder.validator.errorFor('name'));
 * </script>
 * ```
 */
import { createContext } from 'svelte';
import type { Assistant } from "$plugins/assistants/types/assistant/Assistant";
import { ReleaseMode } from "$plugins/assistants/types/assistant/ReleaseMode";
import {
  assistantToApi,
  createEmptyAssistant,
  ASSISTANT_SETTING_KEYS
} from "$plugins/assistants/api/schemas/resources/assistants.schema";
import {avatarToApi} from "$plugins/assistants/api/schemas/resources/assistant-avatars.schema"
import {
  createAssistant,
  updateAssistant,
  deleteAssistant,
  requestAssistantRelease,
  updateAssistantSetting,
  createAssistantPrompts,
  removeAssistantPrompts,
  requestRemix,
  getAssistant,
} from "$plugins/assistants/api/resources/assistantsClient";
import {
  createOrUpdateAssistantAvatar
} from "$plugins/assistants/api/resources/assistantAvatarClient";

import { BuilderValidatorContext } from "./BuilderValidatorContext.svelte.js";
import { clone, valuesEqual, IDENTITY_KEYS, getMaxOutputTokensLimit } from "./builderUtils.js";
import { ApiError } from "$plugins/assistants/api/errors";
import type {ToastContext} from "$lib/components/ui/toast/ToastContext.svelte.js";
import {useStore} from "$lib/app/hooks/useStore.svelte";
import {useApp} from "$lib/app/hooks/useApp.svelte";

export type BuilderMode = "init" | "create" | "edit" | "remix";

const INTENT_STORAGE_KEY = "assistant_builder_intent";

type BuilderIntent =
  | { type: "create" }
  | { type: "edit"; id: string }
  | { type: "remix"; id: string };

/**
 * Hands a builder request off across a route navigation: "open this
 * assistant for edit/remix", or "start a fresh assistant" (the sidebar's
 * "Erstellen" button — an explicit create, which discards any restored
 * session draft instead of resuming it).
 *
 * `BuilderContext` can only be created during its owning layout's component
 * *initialization* — `createContext`'s `set()` wraps Svelte's `setContext`,
 * which throws `set_context_after_init` when called from anywhere else, e.g.
 * an async `onclick` handler on an unrelated page (this is exactly what made
 * the assistant detail page's old `startRemix()`/`startEdit()` silently fail:
 * they called `createBuilderContext()` themselves, inside a click handler,
 * and the resulting error was swallowed by an empty `catch {}`).
 *
 * So a page that wants to jump straight into edit/remix can't create/own a
 * `BuilderContext` itself — it stashes the intent here right before
 * navigating to the builder route, and `BuilderContext.init()` (called from
 * the builder layout's own `onMount`, a valid place) picks it up via
 * {@link consumeBuilderIntent}.
 */
export function requestBuilderIntent(intent: BuilderIntent): void {
  sessionStorage.setItem(INTENT_STORAGE_KEY, JSON.stringify(intent));
}

function consumeBuilderIntent(): BuilderIntent | null {
  const raw = sessionStorage.getItem(INTENT_STORAGE_KEY);
  if (!raw) return null;
  sessionStorage.removeItem(INTENT_STORAGE_KEY);
  try {
    return JSON.parse(raw) as BuilderIntent;
  } catch {
    return null;
  }
}

export class BuilderContext {
  public constructor(
    private readonly toast: ToastContext,
    /** The translator's `__` function, resolved by the caller (component-init
     *  timing) rather than here, mirroring how `AssistantListContext` takes
     *  its `toast`/`app` dependencies as constructor params. */
    private readonly translate: (key: string) => string,
  ) {
    this.validator = new BuilderValidatorContext(() => this.draft, () => this.mode);
  }

  /** Judges whether {@link draft} is valid: completeness checks, session
   *  change-tracking, and inline server field errors. */
  readonly validator: BuilderValidatorContext;

  draft = $state<Assistant>(createEmptyAssistant());
  baseline = $state<Assistant>(createEmptyAssistant());

  mode = $state<BuilderMode>("create");

  /** True while `startNew()` is waiting on the server to mint the record. */
  loading = $state(false);
  error = $state<string | null>(null);

  private debounceTimer: ReturnType<typeof setTimeout> | null = null;
  private readonly DEBOUNCE_MS = 1000;

  private isNewDraft = false;

  /** Set once {@link discardDraft} runs: mutes every reaction of the dying
   *  session — a save still in flight (or its `saveAgain` re-run) failing
   *  against the deleted record must not surface as a save error. */
  private discarded = false;

  /** Whether {@link discardDraft} has run: the session is dead, its draft
   *  deleted — e.g. the exit confirmation never needs to ask again. */
  get isDiscarded(): boolean {
    return this.discarded;
  }

  /** Serializes `updateServer`: an in-flight save plus a "run again" flag so
   *  two cycles never overlap and re-send the same diff. */
  private saving = false;
  private saveAgain = false;

  /** The save cycle currently in flight (if any) — awaited by
   *  {@link flushSave} to drain the pipeline. */
  private currentSave: Promise<void> = Promise.resolve();

  private aiModelStore = useStore('ai-models');
  /** TODO: init what/for? */
  async init() {
    if (this.isNewDraft) {
      return;
    }
    this.mode = "init";

    const intent = consumeBuilderIntent();
    if (intent?.type === "create") {
      // "Erstellen" from the sidebar: always mint a fresh assistant instead
      // of resuming the session draft. startNew() reports its own failures,
      // so on one fall through to the restore path below (same degraded
      // behaviour as a failed edit/remix intent) without toasting twice.
      try {
        await this.startNew();
        return;
      } catch {
        // already toasted by startNew
      }
    } else if (intent) {
      try {
        if (intent.type === "edit") {
          await this.edit(intent.id);
        } else {
          await this.remix(intent.id);
        }
        return;
      } catch (err) {
        const apiErr = ApiError.from(err);
        this.toast.error(`Could not open the assistant. ${apiErr.userMessage}`);
        // Fall through to the normal restore-or-create path below.
      }
    }

    const restored = this.restoreFromSession();
    // const restored = false;
    console.log('restored', restored);
    if (!restored) {
      console.log("could not retrieve from session");
      await this.startNew();
    }
  }

  async startNew(): Promise<void> {
    this.mode = "create";
    this.loading = true;
    this.error = null;
    this.isNewDraft = true;

    try {
      console.log("start building assistant...");
      this.removeStoredData();

      const assistant = await createAssistant(createEmptyAssistant());
      this.begin(assistant, "create");
    } catch (err) {
      const apiErr = ApiError.from(err);
      this.error = apiErr.message;
      this.toast.error(`Could not create a new assistant. ${apiErr.userMessage}`);
    } finally {
      this.loading = false;
      if (this.error) {
        throw Error(this.error);
      }
    }
  }

  /**
   * Open one of the user's own assistants for editing. Refetches the assistant
   * with the privileged `attachments` include (plus the same relationships the
   * detail page loads) so the draft carries already-uploaded knowledge files.
   * The public detail page intentionally omits `attachments` to avoid a 403 for
   * non-creators; this refetch only runs on the owner-only edit path. Mirrors
   * the fetch-then-begin shape of {@see remix} and {@see startNew}.
   */
  async edit(id: string): Promise<void> {
    const detailed = await getAssistant(id, {
      include: [
        "creator",
        "assistant_category",
        "assistant_tags",
        "assistant_avatar",
        "assistant_versions",
        "attachments",
      ],
    });
    this.begin(detailed, "edit");
  }

  /** Start a remix: a fresh record seeded from another assistant, then tweaked. */
  async remix(id: string): Promise<void> {
    const remixAssistant = await requestRemix(id);
    this.begin(remixAssistant, "remix");
  }

  /** Install an assistant as the draft and snapshot an independent baseline. */
  private begin(assistant: Assistant, mode: BuilderMode): void {
    this.mode = mode;
    this.draft = clone(assistant);
    this.baseline = clone(assistant);
    this.setToSession();
    this.validator.init(this.draft);
  }

  readonly changedKeys = $derived.by(() => {
    const out = new Set<keyof Assistant>();
    for (const key of Object.keys(this.draft) as (keyof Assistant)[]) {
      if (IDENTITY_KEYS.has(key)) continue;
      if (!valuesEqual(this.draft[key], this.baseline[key])) out.add(key);
    }
    return out;
  });

  isChanged(key: keyof Assistant): boolean {
    return this.changedKeys.has(key);
  }

  set<K extends keyof Assistant>(key: K, value: Assistant[K]): void {
    this.draft = { ...this.draft, [key]: value };
    this.validator.clearError(key);
    this.setToSession();
    this.scheduleUpdate();
  }

  /**
   * Select a model and, when it actually changes, seed the tunable model
   * params (temperature, top-p) from that model's defaults. This is the one
   * place those values are initialized; afterwards the user can freely adjust
   * the sliders, which write straight to `modelTemp` / `modelTopP` via `set`.
   */
  setModel(modelId: string): void {
    if (modelId === this.draft.model) return;

    const model = this.aiModelStore.models.find((m) => m.id === modelId);
    const patch: Partial<Assistant> = { model: modelId };
    if (model) {
      // `parameters` is a free-form map keyed by the wire's parameter names
      // (`temperature` / `top_p`, not `temp` / `topP`) with `unknown` values —
      // see `wellKnownAiModelParameters` in ai-models.schema.ts. Only seed the
      // draft when the model actually declares a numeric default; otherwise
      // leave whatever the draft already had rather than clobbering it with
      // `undefined` (both fields are required, non-nullable numbers).
      const temperature = model.parameters?.temperature;
      const topP = model.parameters?.top_p;
      if (typeof temperature === 'number') patch.temp = temperature;
      if (typeof topP === 'number') patch.topP = topP;
      // A lower-output model can invalidate a maxTokens value chosen for a
      // bigger one — clamp it down so the draft never exceeds the model's
      // `limits.max_output_tokens` (the slider on the model page uses the same
      // limit as its upper bound). No default is seeded when the model doesn't
      // declare a limit; the existing value is left alone.
      const maxOutputTokens = getMaxOutputTokensLimit(model);
      if (maxOutputTokens !== null && this.draft.maxTokens > maxOutputTokens) {
        patch.maxTokens = maxOutputTokens;
      }
    }

    this.draft = { ...this.draft, ...patch };
    this.validator.clearError("model", "temp", "topP", "maxTokens");
    this.setToSession();
    this.scheduleUpdate();
  }

  private scheduleUpdate(): void {
    if (this.debounceTimer !== null) clearTimeout(this.debounceTimer);
    this.debounceTimer = setTimeout(() => {
      this.debounceTimer = null;
      this.updateServer();
    }, this.DEBOUNCE_MS);
  }

  /** Copy the given keys from draft into baseline, so they're no longer
   *  considered "changed". Done per request as it succeeds, so a later
   *  failure can't make an already-applied change (e.g. a prompt removal)
   *  get re-sent on the next save. */
  private commitKeys(keys: (keyof Assistant)[]): void {
    const patch: Partial<Assistant> = {};
    // `$state.snapshot` unwraps the reactive proxy into a plain value;
    // `structuredClone` (via `clone`) throws on the proxy.
    for (const key of keys)
      patch[key] = $state.snapshot(this.draft[key]) as any;
    this.baseline = { ...this.baseline, ...patch };
    // A successfully saved field can't still be in error.
    this.validator.clearError(...keys);
  }

  private async updateServer(): Promise<void> {
    // A discarded session saves nothing — a stray `saveAgain` re-run after
    // `discardDraft()` would otherwise PATCH (and 404 on) a deleted record.
    if (this.discarded) {
      return;
    }
    // Never let two save cycles overlap: they'd diff against the same
    // not-yet-committed baseline and send duplicate ops. Queue a re-run.
    if (this.saving) {
      this.saveAgain = true;
      return;
    }
    // `updateServer` only ever runs after `begin()` has installed a
    // server-issued draft, so a missing id here means it fired before that —
    // skip rather than send a request the backend can't route.
    if (!this.draft.id) {
      return;
    }
    this.saving = true;
    this.currentSave = this.runUpdateCycle();
    await this.currentSave;
  }

  /** One save cycle — only ever started by {@link updateServer}, which takes
   *  the `saving` lock and publishes the cycle as {@link currentSave} around
   *  it. */
  private async runUpdateCycle(): Promise<void> {
    const draftId = this.draft.id;
    if (!draftId) {
      this.saving = false;
      return;
    }

    // Tracks which single `Assistant` field the in-flight request is for, so
    // a validation error can be routed straight to that field's inline error.
    // Left `undefined` for the main assistant PATCH below, which can touch
    // several fields in one request — that one is routed by parsing the
    // JSON:API error pointer instead (see `reportSaveError`).
    let currentField: keyof Assistant | undefined;

    try {
      // Snapshot the changed keys;
      const changedKeys = new Set(this.changedKeys);

      if (this.isNewDraft) this.isNewDraft = false;

      // Split the changed keys to field updates and relationship groups.
      // Fields are updated on the assistant itself.
      // Relationships are grouped by "relationship assingment", "relationship update", "relationship creation"
      // The groups are:
      // - assistant settings (relationship update )
      // - starter prompts (relationship create/delete)
      // add/remove endpoint, everything else through `assistantToApi`.
      const assistantSettingKeys = ASSISTANT_SETTING_KEYS.filter((key) =>
        changedKeys.has(key),
      );
      for (const settingKey of assistantSettingKeys) {
        changedKeys.delete(settingKey);
        currentField = settingKey;
        await updateAssistantSetting(
          draftId,
          settingKey as "formality" | "language" | "answerStyle",
          this.draft[settingKey] as string,
        );
        this.commitKeys(assistantSettingKeys);
      }

      if (changedKeys.has("starterPrompts")) {
        changedKeys.delete("starterPrompts");
        currentField = "starterPrompts";

        const before = this.baseline.starterPrompts ?? [];
        const after = this.draft.starterPrompts ?? [];
        const added = after.filter((p) => !before.includes(p));
        const removed = before.filter((p) => !after.includes(p));

        if (added.length) {
          await createAssistantPrompts(draftId, added);
        }
        if (removed.length) {
          await removeAssistantPrompts(draftId, removed);
        }
        this.commitKeys(["starterPrompts"]);
      }

      if(changedKeys.has("avatar")){
        changedKeys.delete("avatar");
        currentField = "avatar";
        if(this.draft.avatar){
          const avatar = await createOrUpdateAssistantAvatar(
              draftId,
              avatarToApi(this.draft, this.draft.avatar))
          this.draft.avatar.id = avatar.id;
          this.commitKeys(["avatar"]);
        }
      }

      if (changedKeys.size) {
        currentField = undefined;
        const body = assistantToApi(this.draft, changedKeys);
        await updateAssistant(draftId, body);
        this.commitKeys([...changedKeys]);
      }

    } catch (err) {
      // Surface the error without losing the dirty state so the user can retry.
      this.reportSaveError(err, currentField);
    } finally {
      this.saving = false;
      // Flush any change that arrived while this cycle was in flight.
      if (this.saveAgain) {
        this.saveAgain = false;
        void this.updateServer();
      }
    }
  }

  /**
   * Routes a failed autosave to where the user will actually see it.
   *
   * - Connection/server errors (not validation) always go to a toast — the
   *   save runs in the background with no other UI signal, and there's no
   *   single field to blame.
   * - A validation error on a sub-resource request (settings/prompts/avatar)
   *   is routed straight to the known `fieldKey` — those requests' own
   *   attribute names (`value`, `text`, `icon_css`, ...) belong to a
   *   different JSON:API resource than `assistants` and would never match
   *   `apiFieldToAssistantKey`'s pointer-based lookup.
   * - A validation error on the main assistant PATCH (`fieldKey` undefined,
   *   since it can touch several fields at once) is routed by parsing the
   *   error pointer via `recordServerErrors`. If that can't match any known
   *   field — an unmapped or unexpected pointer — it falls back to a toast
   *   rather than failing silently.
   */
  private reportSaveError(err: unknown, fieldKey?: keyof Assistant): void {
    // A discarded session ignores its own dying saves — the record is gone
    // by design (see `discardDraft`), that's not an error worth surfacing.
    if (this.discarded) {
      return;
    }
    const apiErr = ApiError.from(err);
    this.error = apiErr.message;
    console.error("Failed to save assistant:", apiErr);

    if (!apiErr.isValidation) {
      this.toast.error(`Could not save your changes. ${apiErr.userMessage}`);
      return;
    }

    if (fieldKey) {
      this.validator.recordFieldError(fieldKey, apiErr.fieldErrors[0]?.message ?? apiErr.userMessage);
      return;
    }

    if (!this.validator.recordServerErrors(err)) {
      this.toast.error(`Could not save your changes. ${apiErr.userMessage}`);
    }
  }

  async requestRelease(){
    try {
      if (!(await requestAssistantRelease(this.draft))) {
        this.toast.error(this.translate("assistants.builder.publish.save_failed"));
        return;
      }
      this.draft = { ...this.draft, requested_release_stage: this.draft.releaseStage };
      this.setToSession();
      // A private draft is just saved; the other stages go through review, so
      // say which of the two actually happened.
      this.toast.success(
        this.draft.releaseStage === ReleaseMode.PRIVATE
          ? this.translate("assistants.builder.publish.saved")
          : this.translate("assistants.builder.publish.submitted"),
      );
    } catch (err) {
      const apiErr = ApiError.from(err);
      this.toast.error(`${this.translate("assistants.builder.publish.save_failed")} ${apiErr.userMessage}`);
    }
  }


  /**
   * Saves immediately, cancelling any pending debounced autosave. Used when
   * the user chooses to keep the draft on exit: the server record (and the
   * "Entwürfe" list built on it) must reflect the latest edits before the
   * builder is left.
   *
   * Never rejects: a failed save is reported by the pipeline itself (toast /
   * inline field error) with the dirty state kept — callers that need to know
   * whether everything landed check {@link isDirty} afterwards.
   */
  async flushSave(): Promise<void> {
    if (this.debounceTimer !== null) {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = null;
    }
    // Wait out any cycle still in flight first: entering `updateServer` now
    // would just queue `saveAgain` and return before the flush happened. A
    // cycle re-runs itself while changes arrived mid-save, so looping until
    // `saving` clears drains the whole pipeline; the final `updateServer`
    // below then covers whatever changed after the last snapshot.
    while (this.saving) {
      await this.currentSave;
    }
    await this.updateServer();
  }

  /**
   * Permanently discards the draft: deletes the server-side record and the
   * session copy. Marks the session discarded *before* deleting so an
   * autosave still in flight failing against the deleted record is muted
   * rather than toasted as a save error.
   *
   * Rejects when the server delete fails — callers should keep the user in
   * the builder then, since the draft still exists.
   */
  async discardDraft(): Promise<void> {
    if (this.debounceTimer !== null) {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = null;
    }
    this.discarded = true;
    this.removeStoredData();
    const draftId = this.draft.id;
    if (draftId) {
      await deleteAssistant(draftId);
    }
  }

  readonly isDirty = $derived(this.changedKeys.size > 0);
  STORAGE_KEY = "assistant_draft";
  private setToSession(): void {
    try {
      sessionStorage.setItem(
        this.STORAGE_KEY,
        JSON.stringify({
          draft: this.draft,
          baseline: this.baseline,
          mode: this.mode,
        }),
      );
    } catch {
      console.error("Failed to set assistant in session");
    }
  }

  restoreFromSession(): boolean {
    try {
      const raw = sessionStorage.getItem(this.STORAGE_KEY);
      if (!raw) return false;
      const { draft, baseline, mode } = JSON.parse(raw) as {
        draft: Assistant;
        baseline: Assistant;
        mode: BuilderMode;
      };
      this.draft = draft;
      this.baseline = baseline;
      this.mode = mode;
      this.validator.init(baseline);
      return true;
    } catch {
      console.error("Failed to retrieve assistant in session");
      return false;
    }
  }

  private removeStoredData() {
    sessionStorage.removeItem(this.STORAGE_KEY);
  }

  // Register once when the store is initialized
  private registerUnloadCleanup(): void {
    window.addEventListener("visibilitychange", () => {
      if (
        document.visibilityState === "hidden" &&
        this.isNewDraft &&
        !this.isDirty
      ) {
        const url = `/api/assistants/${this.draft.id}`;
        // sendBeacon survives tab close; fetch() does not
        navigator.sendBeacon(url, JSON.stringify({ _method: "DELETE" }));
      }
    });
  }
}

const [get, set] = createContext<BuilderContext>();

/** Returns the builder session published by the nearest {@link createBuilderContext} ancestor. */
export function useBuilderContext(): BuilderContext {
  const context: BuilderContext | null | undefined = get();
  if (!context) {
    throw new Error('No BuilderContext found in the Svelte context tree. Call createBuilderContext() in a parent component.');
  }
  return context;
}

/**
 * Creates a builder session and publishes it to the component subtree. Call
 * once, in the component that owns the build/edit flow (the `/advance/*`
 * layout); the instance dies with that component.
 *
 * Does **not** fetch/restore — call `.init()` when ready, so the owner
 * decides the timing (mirrors `AssistantListContext.load()`).
 */
export function createBuilderContext(
  toast: ToastContext,
  translate: (key: string) => string,
): BuilderContext {
  const context = new BuilderContext(toast, translate);
  set(context);
  return context;
}
