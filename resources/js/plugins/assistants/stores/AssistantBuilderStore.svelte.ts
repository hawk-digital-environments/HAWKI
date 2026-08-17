import type { Assistant } from "$lib/plugins/assistants/types/assistant/Assistant";
import { ReleaseMode } from "$lib/plugins/assistants/types/assistant/ReleaseMode";
import {
  assistantToApi,
  createEmptyAssistant
} from "$lib/plugins/assistants/api/serializers/assistantSerializer";
import {ASSISTANT_SETTING_KEYS} from "$lib/plugins/assistants/api/serializers/apiFieldSerializer";
import {avatarToApi} from "$lib/plugins/assistants/api/serializers/avatarSerializer"
import {
  // createAssistant,
  // updateAssistant,
  // requestAssistantRelease,
  updateAssistantSetting,
  createAssistantPrompts,
  removeAssistantPrompts,
  // requestRemix,
  getAssistant,
} from "$lib/plugins/assistants/api/resources/assistantsClient";
import {
  createOrUpdateAssistantAvatar
} from "$lib/plugins/assistants/api/resources/assistantAvatarClient";
import {aiModelStore} from "$plugins/core/stores/AiModelStore.svelte";

import { validator } from "$lib/plugins/assistants/stores/AssistantBuilderValidator.svelte";
import { clone, valuesEqual, IDENTITY_KEYS } from "$lib/plugins/assistants/stores/assistantStoreUtils";
import { ApiError } from "$lib/plugins/assistants/api/errors";
import {useToastContext} from "$lib/components/ui/toast/ToastContext.svelte";
import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
const toast = useToastContext();
export type BuilderMode = "init" | "create" | "edit" | "remix";
const {__} = useTranslator();



class AssistantBuilderStore {
  draft = $state<Assistant>(createEmptyAssistant());
  baseline = $state<Assistant>(createEmptyAssistant());

  mode = $state<BuilderMode>("create");

  /** True while `startNew()` is waiting on the server to mint the record. */
  loading = $state(false);
  error = $state<string | null>(null);

  private debounceTimer: ReturnType<typeof setTimeout> | null = null;
  private readonly DEBOUNCE_MS = 1000;

  private isNewDraft = false;

  /** Serializes `updateServer`: an in-flight save plus a "run again" flag so
   *  two cycles never overlap and re-send the same diff. */
  private saving = false;
  private saveAgain = false;

  /** TODO: init what/for? */
  async init() {
    if (this.isNewDraft) {
      return;
    }
    this.mode = "init";
    const restored = this.restoreFromSession();
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

      const assistant = await createAssistant(
        assistantToApi(createEmptyAssistant()),
      );
      this.begin(assistant, "create");
    } catch (err) {
      const apiErr = ApiError.from(err);
      this.error = apiErr.message;
      toast.error(`Could not create a new assistant. ${apiErr.userMessage}`);
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
  async edit(assistant: Assistant): Promise<void> {
    const detailed = await getAssistant(assistant.id, {
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
  async remix(assistant: Assistant): Promise<void> {
    const remixAssistant = await requestRemix(assistant.id);
    this.begin(remixAssistant, "remix");
  }

  /** Install an assistant as the draft and snapshot an independent baseline. */
  private begin(assistant: Assistant, mode: BuilderMode): void {
    this.mode = mode;
    this.draft = clone(assistant);
    this.baseline = clone(assistant);
    this.setToSession();
    validator.init(this.draft);
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
    validator.clearError(key);
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

    const model = aiModelsStore.models.find((m) => m.modelId === modelId);
    const patch: Partial<Assistant> = { model: modelId };
    if (model) {
      patch.temp = model.parameters.temp;
      patch.topP = model.parameters.topP;
    }

    this.draft = { ...this.draft, ...patch };
    validator.clearError("model", "temp", "topP");
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
    validator.clearError(...keys);
  }

  private async updateServer(): Promise<void> {
    // Never let two save cycles overlap: they'd diff against the same
    // not-yet-committed baseline and send duplicate ops. Queue a re-run.
    if (this.saving) {
      this.saveAgain = true;
      return;
    }
    this.saving = true;

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
        await updateAssistantSetting(
          this.draft.id,
          settingKey as "formality" | "language" | "answerLength",
          this.draft[settingKey] as string,
        );
        this.commitKeys(assistantSettingKeys);
      }

      if (changedKeys.has("starterPrompts")) {
        changedKeys.delete("starterPrompts");

        const before = this.baseline.starterPrompts ?? [];
        const after = this.draft.starterPrompts ?? [];
        const added = after.filter((p) => !before.includes(p));
        const removed = before.filter((p) => !after.includes(p));

        if (added.length) {
          await createAssistantPrompts(this.draft.id, added);
        }
        if (removed.length) {
          await removeAssistantPrompts(this.draft.id, removed);
        }
        this.commitKeys(["starterPrompts"]);
      }

      if(changedKeys.has("avatar")){
        changedKeys.delete("avatar");
        //
        if(this.draft.avatar){
          const avatar = await createOrUpdateAssistantAvatar(
              this.draft.id,
              avatarToApi(this.draft, this.draft.avatar))
          this.draft.avatar.id = avatar.id;
          this.commitKeys(["avatar"]);
        }
      }

      if (changedKeys.size) {
        const body = assistantToApi(this.draft, changedKeys);
        await updateAssistant(this.draft.id, body);
        this.commitKeys([...changedKeys]);
      }


    } catch (err) {
      // Surface the error without losing the dirty state so the user can retry.
      // Route any validation errors to their fields for inline display.
      validator.recordServerErrors(err);
      const apiErr = ApiError.from(err);
      this.error = apiErr.message;
      // Validation errors are already shown inline per-field; only the
      // non-validation failures (connection dropped, server error, …) need a
      // toast, since this save runs in the background with no other UI signal.
      if (!apiErr.isValidation) {
        toast.error(`Could not save your changes. ${apiErr.userMessage}`);
      }
      console.error("Failed to save assistant:", apiErr);
    } finally {
      this.saving = false;
      // Flush any change that arrived while this cycle was in flight.
      if (this.saveAgain) {
        this.saveAgain = false;
        void this.updateServer();
      }
    }
  }

  async requestRelease(){
    try {
      if (!(await requestAssistantRelease(this.draft))) {
        toast.error(__("assistants.builder.publish.save_failed"));
        return;
      }
      this.draft = { ...this.draft, requested_release_stage: this.draft.releaseStage };
      this.setToSession();
      // A private draft is just saved; the other stages go through review, so
      // say which of the two actually happened.
      toast.success(
        this.draft.releaseStage === ReleaseMode.PRIVATE
          ? __("assistants.builder.publish.saved")
          : __("assistants.builder.publish.submitted"),
      );
    } catch (err) {
      const apiErr = ApiError.from(err);
      toast.error(`${__("assistants.builder.publish.save_failed")} ${apiErr.userMessage}`);
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
      validator.init(baseline);
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
export const assistantBuilderStore = new AssistantBuilderStore();
