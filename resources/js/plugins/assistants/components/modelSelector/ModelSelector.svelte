<script lang="ts">

    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import {useApp} from "$lib/app/hooks/useApp.svelte";
    import {useStore} from "$lib/app/hooks/useStore.svelte";

    const {
        disabled = false,
        onchange,
    } = $props<{
        disabled?: boolean;
        onchange?: (value: string) => void;
    }>();

    const builder = useBuilderContext();

    const modelStore = useStore('ai-models');
    modelStore.loadData(useApp());

</script>



<div class="input-container renderBlock">
    <label for="modelSelector">Empfohlenes Modell</label>

    <select
            id='modelSelector'
            {disabled}
            onchange={(e) => onchange?.(e.currentTarget.value)}
    >
        {#if !builder.draft.model}
            <option disabled selected value>Select a Model</option>
        {/if}
        {#each modelStore.models as model}
            {#if builder.draft.model &&
                model.id === builder.draft.model}
                <option value={model.id} selected>{model.label}</option>
            {:else}
                <option value={model.id}>{model.label}</option>
            {/if}
        {/each}
    </select>

</div>


<style>

    select {
        --select-border: var(--color-border);

        width: 100%;
        box-sizing: border-box;
        min-height: 2.5rem;
        padding: var(--space-2) var(--space-2_5);
        border-radius: var(--corner-md);
        border: var(--border);
        border-color: var(--select-border);
        background-color: transparent;
        font-size: var(--font-size-sm);
        font-family: inherit;
        color: var(--color-text);
        box-shadow: var(--elevation-1);
        transition:
                border-color var(--duration-fast),
                box-shadow var(--duration-fast);
    }

    select:focus-visible {
        outline: none;
        --select-border: var(--color-focus-ring);
        box-shadow: 0 0 0 1px var(--color-focus-ring);
    }

    select:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }
</style>
