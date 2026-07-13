<script lang="ts">
    import { untrack } from "svelte";
    import Item from './Item.svelte';
    import AddButton from "$plugins/assistants/components/tags/AddButton.svelte";

    interface Props {
        disabled?: boolean;
        defaultValue?: string[];
        /** Label for the collapsed "add" button; defaults to the tag wording ("Add tag"). */
        addItemLabel?: string;
        onchange: (value: string[]) => void;
    }
    const {
        disabled = false,
        defaultValue = [],
        addItemLabel,
        onchange,
    }: Props = $props();

    // eslint-disable-next-line svelte/state_referenced_locally
    let items = $state<string[]>(untrack(() => [...defaultValue]));
    let highlightedItem = $state<string | null>(null);

    // Re-sync local items when the bound value changes from outside — e.g. the
    // draft is loaded/restored after this component has already mounted. Without
    // this, `items` keeps the value captured at construction and never updates.
    $effect(() => {
        const incoming = defaultValue ?? [];
        untrack(() => {
            const same = incoming.length === items.length
                && incoming.every((v, i) => v === items[i]);
            if (!same) items = [...incoming];
        });
    });

    function addTag(value: string): void {
        const normalized: string = value.trim();

        if (!normalized) return;

        const existing: string | undefined = items.find(item => item === normalized);

        if (existing) {
            highlightedItem = existing;
            setTimeout(() => {
                highlightedItem = null;
            }, 1200);
            return;
        }

        items = [...items, normalized];
        onchange(items);
    }

    function removeTag(value: string): void {
        items = items.filter(t => t !== value);
        onchange(items);
    }
</script>

<div class="items-container">
    <div class="items">
        {#each items as tag}
            <Item label={tag}
                  highlighted={highlightedItem === tag}
                  onDelete={() => removeTag(tag)} />
        {/each}
    </div>
    <AddButton
        {addItemLabel}
        onAdd={addTag}
        disabled={disabled} />
</div>


<style>
    .items-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .items:empty {
        display: none;
    }
    .items {
        display: flex;
        flex-direction: column;
        gap: .5rem;

    }
    .items::-webkit-scrollbar {
        display: none;
    }
</style>