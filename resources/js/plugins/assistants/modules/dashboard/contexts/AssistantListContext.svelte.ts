/**
 * # Assistant List Context
 *
 * One paginated, filterable list of assistants, scoped to the component subtree
 * that created it.
 *
 * ## Why a context and not a module-level store
 *
 * The previous `assistantListStore` was a singleton created at import time. That
 * means: its `$state` arrays are allocated as soon as the module is pulled into
 * the bundle, they live for the whole session, and a page holding hundreds of
 * assistants keeps them alive long after the user has navigated away — there is
 * no owner to release them. It also cannot exist twice, so two lists on one page
 * (say "my assistants" and "shared with me") would fight over the same state.
 *
 * A context instance is owned by the component that calls
 * {@link createAssistantListContext}: it is created on mount, garbage-collected
 * on unmount, and can be instantiated once per list. Nothing is retained between
 * page visits.
 *
 * Follow the same shape as `ComposerContext` (see
 * `plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.ts`):
 * a class holding `$state`, a `create*` that wires dependencies and publishes it,
 * and a `use*` that reads it back with an actionable error.
 *
 * ## Usage
 *
 * ```svelte
 * <!-- DashboardIndex.svelte — the owner -->
 * <script lang="ts">
 *     import {useApp} from '$lib/app/hooks/useApp.svelte.js';
 *     import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
 *     import {createAssistantListContext} from './contexts/AssistantListContext.svelte.js';
 *
 *     const list = createAssistantListContext(useApp(), useToastContext());
 *     // kick off the first page; no need to await
 *     list.load();
 * </script>
 *
 * {#if list.loading}
 *     <Spinner />
 * {:else}
 *     {#each list.assistants as assistant (assistant.id)}
 *         <AssistantCard {assistant} />
 *     {/each}
 * {/if}
 * ```
 *
 * ```svelte
 * <!-- any descendant — no props to thread through -->
 * <script lang="ts">
 *     import {useAssistantListContext} from '../contexts/AssistantListContext.svelte.js';
 *     const list = useAssistantListContext();
 *     const canGoNext = $derived(list.currentPage < list.lastPage);
 * </script>
 * ```
 */
import { createContext } from 'svelte';
import type { HawkiApp } from '$lib/kernel/HawkiApp.js';
import type { ToastContext } from '$lib/components/ui/toast/ToastContext.svelte.js';
import { ApiError } from '$plugins/assistants/api/errors';
import { ASSISTANT_LIST_INCLUDES, listAssistants } from '$plugins/assistants/api/resources/assistantsClient';
import type { Assistant } from '$plugins/assistants/types/assistant';

/** The filters the assistants index endpoint supports. Keys are the backend filter names. */
export type AssistantListFilter = {
    name?: string;
    assistant_category?: string[];
    is_favorite?: boolean;
    release_stage?: string[];
};

/** Nested JSON:API filter values, e.g. `filter[assistant_category][text]=x`. */
type JsonApiFilter = Record<string, string | boolean | Record<string, string>>;

const DEFAULT_PAGE_SIZE = 20;

export class AssistantListContext {
    public constructor(
        /** Extra relationships to load on top of {@link ASSISTANT_LIST_INCLUDES}. */
        private readonly extraIncludes: string[],
        /** Where load failures are surfaced. Injected so the context stays testable. */
        private readonly toast: ToastContext
    ) {}

    /** The current page of assistants, already mapped to the domain shape. */
    public assistants = $state<Assistant[]>([]);

    public currentPage = $state(1);
    public lastPage = $state(1);
    public total = $state(0);
    public perPage = $state(DEFAULT_PAGE_SIZE);
    public loading = $state(false);
    /** Last failure message, or `null`. The toast is fired separately. */
    public error = $state<string | null>(null);

    /** 1-based index of the first row on this page; `0` for an empty result. */
    public readonly fromIndex = $derived(
        this.total === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1
    );
    public readonly toIndex = $derived(Math.min(this.currentPage * this.perPage, this.total));

    public readonly hasNextPage = $derived(this.currentPage < this.lastPage);
    public readonly hasPreviousPage = $derived(this.currentPage > 1);

    private filter = $state<AssistantListFilter>({});

    /**
     * Guards against out-of-order responses: every call takes a ticket, and a
     * response only writes state if its ticket is still the newest. Without it a
     * slow page 1 can land after a fast page 2 and overwrite it.
     */
    private requestId = 0;

    /** Replace the active filter and jump back to page 1. */
    public setFilter(filter: AssistantListFilter): Promise<void> {
        this.filter = filter;
        return this.load(1);
    }

    public navigateTo(page: number): Promise<void> {
        return this.load(page);
    }

    public next(): Promise<void> {
        return this.hasNextPage ? this.load(this.currentPage + 1) : Promise.resolve();
    }

    public previous(): Promise<void> {
        return this.hasPreviousPage ? this.load(this.currentPage - 1) : Promise.resolve();
    }

    /** Fetch a page. Defaults to re-fetching the current one. */
    public async load(page: number = this.currentPage): Promise<void> {
        console.log('page', page)
        const ticket = ++this.requestId;
        this.loading = true;
        this.error = null;

        try {
            console.log('loading');
            const {assistants, pagination} = await listAssistants({
                include: [...new Set([...ASSISTANT_LIST_INCLUDES, ...this.extraIncludes])],
                filter: this.buildJsonApiFilter(),
                page: {number: page, size: this.perPage}
            });

            if (ticket !== this.requestId) return;

            this.assistants = assistants;
            this.currentPage = pagination?.page ?? page;
            this.lastPage = pagination?.pages ?? 1;
            this.perPage = pagination?.pageSize ?? this.perPage;
            this.total = pagination?.itemCount ?? assistants.length;

        } catch (err) {
            if (ticket !== this.requestId) return;

            const apiError = ApiError.from(err);
            this.error = apiError.message;
            this.toast.error(apiError.userMessage);
        } finally {
            if (ticket === this.requestId) this.loading = false;
        }
    }

    /**
     * Install a list the caller already has (e.g. handed down from a route load)
     * without a request. Bumps the ticket so any in-flight fetch is discarded.
     */
    public setItems(assistants: Assistant[]): void {
        this.requestId++;
        this.assistants = assistants;
        this.currentPage = 1;
        this.lastPage = 1;
        this.total = assistants.length;
        this.loading = false;
        this.error = null;
    }

    /** Swap one assistant in place — e.g. after a favourite toggle — with no refetch. */
    public updateAssistant(assistant: Assistant): void {
        const index = this.assistants.findIndex(a => a.id === assistant.id);
        if (index !== -1) {
            this.assistants[index] = assistant;
        }
    }

    private buildJsonApiFilter(): JsonApiFilter | undefined {
        const out: JsonApiFilter = {};

        const name = this.filter.name?.trim();
        if (name) out['name'] = name;

        if (this.filter.assistant_category?.length) {
            // Produces filter[assistant_category][text]=...
            out['assistant_category'] = {text: this.filter.assistant_category.join(',')};
        }

        if (this.filter.is_favorite) {
            out['is_favorite'] = this.filter.is_favorite;
        }

        if (this.filter.release_stage?.length) {
            out['release_stage'] = this.filter.release_stage.join(',');
        }

        return Object.keys(out).length ? out : undefined;
    }
}

const [get, set] = createContext<AssistantListContext>();

/** Returns the list published by the nearest {@link createAssistantListContext} ancestor. */
export function useAssistantListContext(): AssistantListContext {
    const context: AssistantListContext | null | undefined = get();
    if (!context) {
        throw new Error('No AssistantListContext found in the Svelte context tree. Call createAssistantListContext() in a parent component.');
    }
    return context;
}

/**
 * Creates a list context and publishes it to the component subtree. Call once,
 * in the component that owns the list; the instance dies with that component.
 *
 * Does **not** fetch — call `.load()` when you want the first page, so the owner
 * decides whether to fetch immediately, wait for a filter, or seed via
 * `setItems()`.
 *
 * @param app The running app. Reserved for config/translator needs; the request
 *            itself goes through `assistantsClient`, which resolves the app via
 *            `useApp()` internally.
 * @param toastContext Where load failures are surfaced.
 * @param extraIncludes Relationships to load beyond {@link ASSISTANT_LIST_INCLUDES}.
 */
export function createAssistantListContext(
    app: HawkiApp,
    toastContext: ToastContext,
    extraIncludes: string[] = []
): AssistantListContext {
    const context = new AssistantListContext(extraIncludes, toastContext);
    set(context);
    return context;
}
