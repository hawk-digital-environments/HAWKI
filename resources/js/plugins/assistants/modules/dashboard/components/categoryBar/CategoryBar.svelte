<script lang="ts">

    import CategoryButton from "./CategoryButton.svelte";
    import {assistantOptionsStore} from "$plugins/assistants/stores/AssistantOptionsStore.svelte.js";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";

    const {__} = useTranslator();



    let categories = $derived(assistantOptionsStore.categories);
    let {
        activeFilters = $bindable(new Set<string>()),
    } = $props<{
        activeFilters?: Set<string>;
    }>();

    function toggleFilter(category: string) {
        const next = new Set(activeFilters);
        next.has(category) ? next.delete(category) : next.add(category);
        activeFilters = next;
    }

    function resetFilters() {
        activeFilters = new Set();
    }
</script>

<div class="categories">
    <div class="categories-list">
        <CategoryButton
                label={__("assistants.categories.all")}
                isActive={activeFilters.size === 0}
                onchange={resetFilters}
        />
        {#each categories as category}
            <CategoryButton
                    label={__(category.text)}
                    isActive={activeFilters.has(category.text)}
                    onchange={() => toggleFilter(category.text)}
            />
        {/each}
    </div>
</div>

<style>
    .categories {
        display: grid;
        align-items: center;
    }
    .categories-list {
        display: flex;
        flex-direction: row;
        gap: var(--space-2);
        overflow-x: auto;
        padding: var(--space-0_5);
    }
    .categories-list::-webkit-scrollbar {
        display: none;
    }
</style>
