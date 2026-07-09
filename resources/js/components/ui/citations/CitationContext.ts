import {getContext, setContext} from 'svelte';
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';

interface Events {
    focusCitation: string;
}

export class CitationContext {
    private flow = new SyncPipeline<Events>();

    public focusCitation(citationId: string): void {
        this.flow.trigger('focusCitation', citationId);
    }

    public onFocusCitation(citationId: string, callback: () => void): () => void {
        return this.flow.on('focusCitation', (id) => {
            if (id === citationId) {
                callback();
            }
        });
    }
}

const citationKey = Symbol('citation');

export function createCitationContext(): CitationContext {
    const context = new CitationContext();
    setContext(citationKey, context);
    return context;
}

export function useCitationContext(): CitationContext {
    const context = getContext<CitationContext>(citationKey);
    if (!context) {
        throw new Error('CitationContext not found. Make sure to call createCitationContext in a parent component.');
    }
    return context;
}
