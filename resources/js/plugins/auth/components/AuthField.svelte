<!--
  @component One labelled auth form field: the label, the input, and the hint or
  validation error below it — with the accessibility wiring (label/for, aria-invalid,
  aria-describedby, and the readonly/aria-disabled/aria-busy pending triplet) owned
  in one place instead of re-proven by hand on every page.

  The error replaces the hint while set and marks the field invalid; `pending` keeps
  the input readable (readonly, not disabled) while a request is in flight. Everything
  else (type, pattern, autocomplete, placeholder, …) passes through to the Input.
-->
<script lang="ts">
    import type { HTMLInputAttributes } from 'svelte/elements';
    import Input from '$lib/components/ui/input/Input.svelte';

    interface Props extends HTMLInputAttributes {
        id: string;
        /** Field label; becomes the input's accessible name via label/for. */
        label: string;
        /** Validation error; replaces the hint and marks the field invalid while set. */
        error?: string;
        /** Static hint, shown while there is no error. */
        hint?: string;
        /** While pending the input is readonly and announced busy/disabled. */
        pending?: boolean;
        value?: string;
        ref?: HTMLInputElement | null;
    }

    let {
        id,
        label,
        error = undefined,
        hint = undefined,
        pending = false,
        value = $bindable(''),
        ref = $bindable(undefined),
        ...restProps
    }: Props = $props();

    const describedBy = $derived(
        [
            error !== undefined ? `${id}-error` : null,
            error === undefined && hint !== undefined ? `${id}-help` : null
        ].filter((elementId): elementId is string => elementId !== null).join(' ') || undefined
    );
</script>

<div class="auth-field">
    <label for={id}>{label}</label>
    <Input
        {id}
        bind:value
        bind:ref
        {...restProps}
        readonly={pending || undefined}
        aria-disabled={pending || undefined}
        aria-busy={pending || undefined}
        aria-invalid={error !== undefined || undefined}
        aria-describedby={describedBy}
    />
    {#if error !== undefined}
        <small id="{id}-error" class="auth-error" role="alert">{error}</small>
    {:else if hint !== undefined}
        <small id="{id}-help" class="auth-hint">{hint}</small>
    {/if}
</div>
