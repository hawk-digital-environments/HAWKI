<!--
  @component Read-only field display for the review's step view: a label
  above a paper tile (the builder input's metrics, MUI-Paper elevation via
  layered shadow tokens). Fields the submission changed get an accent
  outline and full elevation; unchanged ones flatten and fade slightly — a
  lighter disabled look.
-->
<script lang="ts">
    interface Props {
        /** The field label (a translated builder label key works directly). */
        label: string;
        /** The value to display; `null`/empty renders as an em dash. */
        value?: string | null;
        /** Optional hint below the value. */
        hint?: string;
        /** Prose values keep line breaks; lists arrive pre-joined. */
        prose?: boolean;
        /** The submission changed this field: accent outline + elevation. */
        changed?: boolean;
    }

    let {label, value, hint, prose = false, changed = false}: Props = $props();

    const hasValue = $derived(null !== value && undefined !== value && '' !== value);
</script>

<div class="field">
    <p class="label" class:changed>{label}</p>
    <div class="paper" class:changed class:empty={!hasValue}>
        <p class="value" class:prose>{hasValue ? value : '—'}</p>
    </div>
    {#if hint}
        <p class="hint">{hint}</p>
    {/if}
</div>

<style>
    .field {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        min-width: 0;
    }

    .label {
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }

    .label.changed {
        color: var(--color-text);
    }

    /* The builder input's chrome (same radius and border — see
       Input.svelte/Textarea.svelte) on a paper tile. Unchanged fields sit
       flat and slightly faded (a lighter disabled state); changed fields
       carry the MUI-Paper lift (layered elevation shadows) plus a soft
       accent outline, like a persistent focus ring. */
    .paper {
        box-sizing: border-box;
        border-radius: var(--corner-md);
        border: var(--border);
        background-color: var(--color-surface-raised);
        box-shadow: none;
        padding: var(--space-2) var(--space-3);
        opacity: 0.72;
        transition:
            box-shadow var(--duration-fast) var(--easing-out),
            opacity var(--duration-fast) var(--easing-out),
            outline-color var(--duration-fast) var(--easing-out);
        outline: 1px solid transparent;
        outline-offset: -1px;
    }

    .paper.changed {
        opacity: 1;
        box-shadow: var(--elevation-1), var(--elevation-2);
        outline-color: color-mix(in oklab, var(--color-accent-500) 45%, transparent);
    }

    .value {
        margin: 0;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        color: var(--color-text);
        overflow-wrap: anywhere;
    }

    .value.prose {
        white-space: pre-wrap;
    }

    .paper.empty .value {
        color: var(--color-text-muted);
    }

    .hint {
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
</style>
