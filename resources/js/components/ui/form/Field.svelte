<script lang="ts">
    import type {Snippet} from 'svelte';
    let {id, label, error, hint, children}: {id: string; label: string; error?: string; hint?: string; children: Snippet<[{id: string; 'aria-invalid': boolean; 'aria-describedby': string | undefined}]>} = $props();
</script>

<div class="field">
    <label for={id}>{label}</label>
    {@render children({id, 'aria-invalid': !!error, 'aria-describedby': error ? `${id}-error` : hint ? `${id}-hint` : undefined})}
    {#if hint}<p id={`${id}-hint`} class="hint">{hint}</p>{/if}
    {#if error}<p id={`${id}-error`} class="error">{error}</p>{/if}
</div>

<style>
    .field { display: flex; flex-direction: column; gap: var(--space-2); min-width: 0; }
    label { font-size: var(--font-size-sm); font-weight: 600; }
    p { margin: 0; font-size: var(--font-size-sm); }
    .hint { color: var(--color-text-muted); }
    .error { color: var(--color-error); font-weight: 600; }
</style>
