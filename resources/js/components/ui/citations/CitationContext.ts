import {getContext, setContext} from 'svelte';
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';

interface Events {
    focusCitation: string;
}

/**
 * Shared context for the `citations` component family (`CitationRoot`,
 * `CitationList`, `Citation`, `CitationReference`). It is the only channel
 * that connects the two places a single citation is rendered:
 *
 * - `CitationReference` — the inline `[N]` chip injected into the message
 *   markdown (see `injectCitationsIntoMarkdown`), rendered wherever the
 *   citation's text range ends
 * - `Citation` — the source tile listed further down in a `CitationList`
 *
 * Both sides only know the citation's `identifier` (see
 * {@link EnrichedUrlCitation} in `types.ts`); they never reference each other
 * directly. Clicking a `CitationReference` chip calls {@link focusCitation},
 * and every mounted `Citation` tile listens via {@link onFocusCitation} for
 * its own identifier to scroll into view and flash-highlight.
 *
 * `CitationRoot` creates one instance per rendered message via
 * {@link createCitationContext} and provides it through Svelte context, so
 * `CitationReference` and `Citation` — which may live in very different parts
 * of the tree (markdown body vs. citation list) — can reach the same
 * instance via {@link useCitationContext} without prop drilling.
 */
export class CitationContext {
    private flow = new SyncPipeline<Events>();

    /**
     * Requests that the citation tile with the given identifier scroll into
     * view and flash-highlight. Called by `CitationReference` when its chip
     * is clicked.
     */
    public focusCitation(citationId: string): void {
        this.flow.trigger('focusCitation', citationId);
    }

    /**
     * Registers `callback` to run whenever {@link focusCitation} is called
     * with a matching `citationId`. Called by `Citation` in `onMount`; the
     * returned function unregisters the listener (call it on unmount /
     * cleanup).
     */
    public onFocusCitation(citationId: string, callback: () => void): () => void {
        return this.flow.on('focusCitation', (id) => {
            if (id === citationId) {
                callback();
            }
        });
    }
}

const citationKey = Symbol('citation');

/**
 * Creates a new {@link CitationContext} and provides it via Svelte context
 * for the current component subtree. Call once per message, from
 * `CitationRoot`'s `<script>` — every `CitationReference` and `Citation`
 * rendered inside that root's `children` snippet will then share the same
 * instance.
 */
export function createCitationContext(): CitationContext {
    const context = new CitationContext();
    setContext(citationKey, context);
    return context;
}

/**
 * Retrieves the {@link CitationContext} set by an ancestor `CitationRoot`.
 * Throws if no `CitationRoot` is present in the component tree above the
 * caller — `CitationReference` guards against this (a message can be
 * rendered outside of citation markup, e.g. plain text) by catching the
 * error and falling back to a toast, since it is optional there; `Citation`
 * does not guard, because it is only ever rendered inside a `CitationRoot`.
 */
export function useCitationContext(): CitationContext {
    const context = getContext<CitationContext>(citationKey);
    if (!context) {
        throw new Error('CitationContext not found. Make sure to call createCitationContext in a parent component.');
    }
    return context;
}
