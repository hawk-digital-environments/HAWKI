<script lang="ts">

import {aiModelsStore} from "$lib/stores/AiModelsStore.svelte.js";
import type {AiModel} from "$lib/plugins/assistants/types/aiModel/AiModel";
import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";

const {
    disabled = false,
    onchange,
} = $props<{
    disabled?: boolean;
    onchange?: (value: string) => void;
}>();

const builder = useBuilderContext();

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
        {#each aiModelsStore.models as model}
            {#if builder.draft.model &&
                model.modelId === builder.draft.model}
                <option value={model.modelId} selected>{model.label}</option>
            {:else}
                <option value={model.modelId}>{model.label}</option>
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
