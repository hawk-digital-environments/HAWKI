<!--
  @component Switch inside a table cell that saves one boolean-like change of
  the row through the surrounding AdminWorkspace as soon as it is flipped.
  `changes` returns the field values for the new state, e.g. `{active: true}`.
-->
<script lang="ts">
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import type { AdminRow } from '../schemas/admin-content.js';
    import { useAdminWorkspace } from '../workspace.js';

    const {
        row,
        checked,
        label,
        changes
    }: {
        row: AdminRow;
        checked: boolean;
        label: string;
        changes: (enabled: boolean) => Record<string, unknown>;
    } = $props();
    const workspace = useAdminWorkspace();
    const locked = $derived(workspace().busy || workspace().updating.includes(row.id));
    let pending = $state(false);

    async function toggle() {
        if (locked) return;
        pending = true;
        try {
            await workspace().update(row, changes(!checked));
        } finally {
            pending = false;
        }
    }
</script>

<button
    type="button"
    role="switch"
    class="row-switch"
    aria-checked={checked}
    aria-label={label}
    aria-busy={pending || undefined}
    disabled={locked}
    onclick={toggle}
>
    <Switch
        {checked}
        presentational
        disabled={locked}
    />
</button>

<style>
    .row-switch {
        display: inline-flex;
        align-items: center;
        padding: var(--space-1);
        border: 0;
        border-radius: var(--corner-full);
        background: none;
        cursor: pointer;
    }
    .row-switch:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
    }
    .row-switch:disabled {
        cursor: not-allowed;
    }
    .row-switch[aria-busy='true'] {
        opacity: 0.5;
    }
</style>
