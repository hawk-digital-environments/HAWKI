import z from "zod";
import {
  AssistantSettingSchema,
  CategorySchema,
  TagSchema,
  type AssistantSetting,
  type Category,
  type Tag,
} from "$plugins/assistants/types/assistant";
import { useToastContext } from "$lib/components/ui/toast/ToastContext.svelte.js";

import {
  listCategories,
  listTags,
  listSettings,
  createTag,
} from "$plugins/assistants/api/resources/assistantOptionsClient";
import { ApiError } from "$plugins/assistants/api/errors";
import {DataStore} from "$lib/kernel/stores/types";

/**
 * Shape of the `localStorage` cache written by {@link AssistantOptionsStore.load}.
 * Parsed rather than cast on read: the cache outlives deploys, so an entry
 * written by an older build can have a stale option shape.
 */
const CachedOptionsSchema = z.object({
  categories: z.array(CategorySchema),
  tags: z.array(TagSchema),
  settings: z.array(AssistantSettingSchema),
});

/**
 * The option lists the builder presents for an assistant's relationships
 * (category, language, tags). Loaded once per session via `load()`; consumed
 * directly as `assistantOptions.categories` etc.
 *
 * Tags are the only user-creatable option — `addTag()` persists a new tag on
 * the server and folds it into the local list so it is immediately selectable.
 */
class AssistantOptionsStore implements DataStore{
    public readonly name = "assistant-options-store";

    static cacheKey = "assistant-options";
    categories = $state<Category[]>([]);
    tags = $state<Tag[]>([]);
    settings = $state<AssistantSetting[]>([]);

    loaded = $state(false);
    loading = $state(false);
    error = $state<string | null>(null);

    constructor() {
    this.restoreFromCache();
    }

    /** Seed the lists from the `localStorage` cache so the builder can render
    *  before `load()` finishes. A cache that no longer matches the current
    *  option shape is dropped, leaving the store to fetch fresh lists. */
    private restoreFromCache(): void {
    if (typeof window === "undefined") return;

    const cached = localStorage.getItem(AssistantOptionsStore.cacheKey);
    if (!cached) return;

    let payload: unknown;
    try {
      payload = JSON.parse(cached);
    } catch {
      localStorage.removeItem(AssistantOptionsStore.cacheKey);
      return;
    }

    const parsed = CachedOptionsSchema.safeParse(payload);
    if (!parsed.success) {
      console.warn(
        "Discarding the cached assistant options: they do not match the current shape.",
        parsed.error,
      );
      localStorage.removeItem(AssistantOptionsStore.cacheKey);
      return;
    }

    this.categories = parsed.data.categories;
    this.tags = parsed.data.tags;
    this.settings = parsed.data.settings;
    this.loaded = true;
    }

    /** Fetch all option lists in parallel. No-op once loaded unless `force`. */
    async load(force = false): Promise<void> {
    if (this.loading || (this.loaded && !force)) return;
    this.loading = true;
    this.error = null;

    try {
      const [categories, tags, settings] = await Promise.all([
        listCategories(),
        listTags(),
        listSettings(),
      ]);
      this.categories = categories;
      this.tags = tags;
      this.settings = settings;
      this.loaded = true;

      localStorage.setItem(
        AssistantOptionsStore.cacheKey,
        JSON.stringify({
          categories: this.categories,
          tags: this.tags,
          settings: this.settings,
        }),
      );
    } catch (err) {
      const apiErr = ApiError.from(err);
      this.error = apiErr.message;
      useToastContext().error(apiErr.userMessage);
    } finally {
          this.loading = false;
          if (this.error) {
            throw Error(this.error);
          }
        }
    }

    async addTag(assistantId: string, normalizedTag: string): Promise<Tag> {
        const existing = this.tags.find((t) => t.text === normalizedTag);
        if (existing) return existing;

        const tag = await createTag(assistantId, normalizedTag);
        this.tags = [...this.tags, tag];
        return tag;
    }

    getSetting(key: string): AssistantSetting | undefined {
        return this.settings.find((s) => s.key === key);
    }
}

export const assistantOptionsStore = new AssistantOptionsStore();
