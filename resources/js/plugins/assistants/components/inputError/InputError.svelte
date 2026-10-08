<script lang="ts">

    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    // Inline validation message shown below a form field.
    // Renders nothing unless there is a message, so callers can place it
    // unconditionally after the field.
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
        align-items: flex-start;
        gap: .25rem;
        color: rgb(217, 45, 32);
        font-size: var(--font-size-xs);
        line-height: 1.2;
    }
    /* The icon box is one text line tall, so the glyph centres on the first
       line and stays there when the message wraps. */
    .input-error .icon {
        display: flex;
        align-items: center;
        height: calc(var(--font-size-xs) * 1.2);
        font-size: var(--font-size-sm);
        flex-shrink: 0;
    }
</style>
