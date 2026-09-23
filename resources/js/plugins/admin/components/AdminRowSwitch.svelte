<!--
  @component Switch inside a table cell that saves as soon as it is flipped.
  `onToggle` receives the new state and does the write, e.g.
  `onToggle={(enabled) => mutations.update(row, {active: enabled})}`; the
  switch shows as busy until the returned promise settles.
-->
<script lang="ts">
    import Switch from '$lib/components/ui/switch/Switch.svelte';

    const {
        checked,
        label,
        disabled = false,
        onToggle
    }: {
        checked: boolean;
        label: string;
        disabled?: boolean;
        onToggle: (enabled: boolean) => Promise<void> | void;
    } = $props();
    let pending = $state(false);

    async function toggle() {
        if (disabled || pending) return;
        pending = true;
        try {
            await onToggle(!checked);
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
    {disabled}
    onclick={toggle}
>
    <Switch
        {checked}
        presentational
        {disabled}
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
