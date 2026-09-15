import type { RestApi } from '$lib/kernel/api/RestApi.js';
import { ModelInspectionSchema, ProviderDiscoverySchema } from '../schemas/admin-actions.js';

export type ModelSuggestion = { value: string; label: string };

export interface ModelLookupOptions {
    restApi: RestApi;
    /** Provider whose models to discover; null, undefined or '' switches discovery off. */
    providerId: () => unknown;
    /** Model id currently in the form; inspection results only apply while it still matches. */
    modelId: () => unknown;
    /** Receives provider metadata (label, limits, …) to merge into the form. */
    adopt: (values: Record<string, unknown>) => void;
}

/**
 * Looks up provider models for the editor: suggests model ids and pulls a picked model's metadata.
 * Construct it during component initialisation: discovery runs in an effect keyed on the provider.
 */
export class ModelLookup {
    suggestions = $state<ModelSuggestion[] | null>(null);
    suggesting = $state(false);
    failed = $state(false);
    inspecting = $state(false);
    inspected = $state<'done' | 'failed' | null>(null);
    readonly #options: ModelLookupOptions;
    readonly #discovered = new Map<string, ModelSuggestion[]>();
    #inspection = 0;

    constructor(options: ModelLookupOptions) {
        this.#options = options;
        $effect(() => {
            const providerId = this.#providerKey();
            this.suggestions = null;
            this.suggesting = false;
            this.failed = false;
            // A pending inspection belongs to the previous provider; its response must not be adopted.
            this.#inspection++;
            this.inspecting = false;
            this.inspected = null;
            if (providerId === null) return;
            const cached = this.#discovered.get(providerId);
            if (cached) {
                this.suggestions = cached;
                return;
            }
            let stale = false;
            this.suggesting = true;
            options.restApi
                .postToResourceAction(
                    'admin-providers',
                    `${encodeURIComponent(providerId)}/actions/discover`,
                    {},
                    { schema: ProviderDiscoverySchema }
                )
                .then((response) => {
                    const models = response.models.map((model) => ({ value: model.model_id, label: model.label }));
                    this.#discovered.set(providerId, models);
                    if (!stale) this.suggestions = models;
                })
                .catch((failure) => {
                    console.warn('Model discovery failed.', failure);
                    if (!stale) {
                        this.suggestions = [];
                        this.failed = true;
                    }
                })
                .finally(() => {
                    if (!stale) this.suggesting = false;
                });
            return () => {
                stale = true;
            };
        });
    }

    #providerKey(): string | null {
        const providerId = this.#options.providerId();
        return providerId == null || providerId === '' ? null : String(providerId);
    }

    /** Translation key for the model id hint, following discovery and inspection progress. */
    get hint(): string {
        if (this.#providerKey() === null) return 'admin.model_id_provider_first';
        if (this.suggesting) return 'admin.model_id_loading';
        if (this.failed) return 'admin.model_id_discovery_failed';
        if (this.inspecting) return 'admin.model_id_inspecting';
        if (this.inspected)
            return this.inspected === 'done' ? 'admin.model_id_inspected' : 'admin.model_id_inspect_failed';
        return this.suggestions?.length ? 'admin.model_id_suggestions' : 'admin.model_id_no_suggestions';
    }

    /** Translation key for the live region announcing inspection progress; empty while idle. */
    get status(): string {
        if (this.inspecting) return 'admin.model_id_inspecting';
        if (this.inspected === 'done') return 'admin.model_id_inspected';
        if (this.inspected === 'failed') return 'admin.model_id_inspect_failed';
        return '';
    }

    /** Copies the suggestion's display label when the typed model id matches one. */
    adoptLabel(modelId: unknown) {
        const match = this.suggestions?.find((suggestion) => suggestion.value === modelId);
        if (match) this.#options.adopt({ label: match.label });
    }

    /** A picked suggestion also pulls the provider's metadata for the remaining fields. */
    async inspect(modelId: string) {
        this.adoptLabel(modelId);
        const providerId = this.#providerKey();
        if (providerId === null) return;
        const token = ++this.#inspection;
        this.inspecting = true;
        this.inspected = null;
        try {
            const response = await this.#options.restApi.postToResourceAction(
                'admin-providers',
                `${encodeURIComponent(providerId)}/actions/inspect`,
                { model_id: modelId },
                { schema: ModelInspectionSchema }
            );
            if (token !== this.#inspection) return;
            if (this.#providerKey() === providerId && this.#options.modelId() === modelId) {
                this.#options.adopt(response.model);
            }
            this.inspected = 'done';
        } catch (failure) {
            console.warn('Model inspection failed.', failure);
            if (token === this.#inspection) this.inspected = 'failed';
        } finally {
            if (token === this.#inspection) this.inspecting = false;
        }
    }
}
