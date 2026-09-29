<script lang="ts">
    import CheckmarkCircle02Icon from '$lib/components/ui/icons/iconset/CheckmarkCircle02Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {AppliedField} from '../../types';

    /** The builder fields the guide filled in with this reply. */
    let {fields}: { fields: AppliedField[] } = $props();

    const {__} = useTranslator();
</script>

<!-- A quiet summary under the reply: one chip per filled field, each opening
     the builder step that shows it and scrolling to the field. -->
<div class="applied">
    <p class="heading">
        <span class="icon" aria-hidden="true"><CheckmarkCircle02Icon size="1em"/></span>
        {__('assistants.builder.guide.applied')}
    </p>
    <ul class="fields">
        {#each fields as field (field.label)}
            <li>
                {#if field.open}
                    <button type="button" class="field"
                            title={__('assistants.builder.guide.open_field', {field: field.label})}
                            onclick={field.open}>{field.label}</button>
                {:else}
                    <span class="field">{field.label}</span>
                {/if}
            </li>
        {/each}
    </ul>
</div>

<style>
    .applied {
        display: flex;
        flex-direction: column;
        gap: var(--space-1_5);
    }

    .heading {
        display: flex;
        align-items: center;
        gap: var(--space-1);
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }

    .icon {
        display: inline-flex;
        font-size: var(--font-size-sm);
    }

    .fields {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-1);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .field {
        display: inline-flex;
        padding: var(--space-0_5) var(--space-2);
        border: none;
        border-radius: var(--corner-full);
        background: var(--color-surface-light);
        color: var(--color-text);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
    }

    button.field {
        cursor: pointer;
        transition: background-color var(--duration-fast);
    }

    button.field:hover {
        background: var(--color-hover);
    }

    button.field:focus-visible {
        outline: var(--focus-outline);
        outline-offset: 2px;
    }
</style>
