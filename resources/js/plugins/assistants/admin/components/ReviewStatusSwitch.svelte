<!--
  @component Compact 3-way segmented control for an attachment's admin review
  verdict (ok / corrupted / inadequate). `RadioSwitch`/`RadioOption` exist but
  only in a vertical, card-per-option layout meant for a handful of full-width
  choices (e.g. release stage) — not a tight inline row next to a file name,
  so this is a small, purpose-built control instead.
-->
<script lang="ts">
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import type { AttachmentReviewStatus } from '$plugins/assistants/types/UploadFile';

    const {
        value,
        disabled = false,
        onchange
    }: {
        value: AttachmentReviewStatus | null | undefined;
        disabled?: boolean;
        onchange: (status: AttachmentReviewStatus) => void;
    } = $props();
    const { __ } = useTranslator();

    const options: AttachmentReviewStatus[] = ['ok', 'corrupted', 'inadequate'];
</script>

<div
    class="review-status-switch"
    role="radiogroup"
    aria-label={__('admin.fields.review_status')}
>
    {#each options as option (option)}
        <button
            type="button"
            role="radio"
            aria-checked={value === option}
            class="option option-{option}"
            class:active={value === option}
            {disabled}
            onclick={() => onchange(option)}
        >
            {__('admin.values.' + (option === 'ok' ? 'attachment_ok' : option))}
        </button>
    {/each}
</div>

<style>
    .review-status-switch {
        display: inline-flex;
        border: var(--border);
        border-radius: var(--corner-full);
        padding: 2px;
        gap: 2px;
    }
    .option {
        border: none;
        background: transparent;
        border-radius: var(--corner-full);
        padding: var(--space-1) var(--space-3);
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        cursor: pointer;
        white-space: nowrap;
    }
    .option:not(:disabled):hover {
        background: var(--color-hover);
    }
    .option:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }
    .option.active.option-ok {
        background: var(--color-success);
        color: var(--color-on-interactive);
    }
    .option.active.option-corrupted,
    .option.active.option-inadequate {
        background: var(--color-error);
        color: var(--color-on-interactive);
    }
</style>
