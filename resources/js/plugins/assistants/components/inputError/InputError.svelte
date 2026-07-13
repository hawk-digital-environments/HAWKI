<script lang="ts">

    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    // Inline validation indicator shown in front of an input's label.
    // Renders nothing unless there is a message, so callers can place it
    // unconditionally next to the label.
    let { message }: { message?: string } = $props();
</script>

{#if message}
    <span class="input-error" role="alert" title={message}>
        <span class="icon"><AlertCircleIcon size="1em" /></span>
        <span class="text">{message}</span>
    </span>
{/if}

<style>
    .input-error {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        color: rgb(217, 45, 32);
        font-size: var(--font-size-xs);
        line-height: 1.2;
    }
    .input-error .icon {
        font-size: var(--font-size-sm);
        flex-shrink: 0;
    }
</style>
