import { flushSync } from 'svelte';

// Mirrors AssistantListContext: private `filter` is $state, and load() reads it
// back synchronously (via buildJsonApiFilter) in the same tick setFilter wrote it.
class ListCtx {
    filter = $state({});
    perPage = $state(20);
    loading = $state(false);
    assistants = $state([]);

    setFilter(f) { this.filter = f; return this.load(1); }

    buildFilter() { const o = {}; if (this.filter.name) o.name = this.filter.name; return o; }

    async load(page) {
        this.loading = true;
        const args = {                       // <- built synchronously, inside the effect
            filter: this.buildFilter(),      // <- READS this.filter
            page: { number: page, size: this.perPage }
        };
        await Promise.resolve();             // stand-in for the network call
        this.assistants = [];
        this.perPage = args.page.size;
        this.loading = false;
    }
}

export function run(label, useUntrack, filterIsState) {
    const ctx = new ListCtx();
    if (!filterIsState) { /* emulate plain field */ }
    let runs = 0;
    const cleanup = $effect.root(() => {
        let searchQuery = $state('');
        let activeFilters = $state(new Set());
        $effect(() => {
            if (++runs > 25) return;              // bail out so we don't hang
            const name = searchQuery;
            const cats = [...activeFilters];
            const payload = { name, assistant_category: cats, release_stage: ['a', 'b'] };
            if (useUntrack) {
                $effect.tracking();               // no-op, keeps shape similar
                untrackCall(() => ctx.setFilter(payload));
            } else {
                ctx.setFilter(payload);
            }
        });
    });
    flushSync();
    return { label, runs };
}

import { untrack as untrackCall } from 'svelte';
