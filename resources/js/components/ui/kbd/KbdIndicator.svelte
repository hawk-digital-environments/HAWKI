<!--
  @component The keycap rendered by `Kbd`. Not used standalone: it reads its
  label and visibility from the context the surrounding `Kbd` provides, so it
  only works inside a `Kbd` children snippet (or as `Kbd`'s own default
  rendering). See `Kbd` for the full story.
-->
<script module lang="ts">
    export const KBD_CONTEXT_KEY = Symbol('kbd');

    export interface KbdContext {
        /** Full combination label, e.g. `Ctrl + Shift + A`. */
        readonly label: string;
        /** Whether the keycap should currently be in the DOM. */
        readonly visible: boolean;
    }
</script>
<script lang="ts">
    import {getContext} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';

    const context = getContext<KbdContext>(KBD_CONTEXT_KEY);

    let {...restProps}: HTMLAttributes<HTMLElement> = $props();
</script>

{#if context.visible}
    <kbd aria-hidden="true" {...mergeProps({class: 'kbd'}, restProps)}>{context.label}</kbd>
{/if}

<style>
    .kbd {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-family: inherit;
        font-size: var(--font-size-xxs);
        color: var(--color-text-muted);
        background: var(--color-surface);
        border: var(--border);
        border-radius: var(--corner-sm);
        padding: 0 var(--space-1);
        line-height: 1.4;
        pointer-events: none;
        user-select: none;
    }
</style>
