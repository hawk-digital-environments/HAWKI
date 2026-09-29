<script lang="ts">
    import type {IngestFile} from '../../types';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {getFileIconSvg} from '$lib/utils/fileIconSvg.js';
    import RadialProgress from '$lib/components/ui/radial-progress/RadialProgress.svelte';
    import ShimmerText from '$lib/components/ui/shimmer-text/ShimmerText.svelte';
    import CheckmarkCircle02Icon from '$lib/components/ui/icons/iconset/CheckmarkCircle02Icon.svelte';
    import Alert02Icon from '$lib/components/ui/icons/iconset/Alert02Icon.svelte';
    import Tick02Icon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';
    import {formatFileSize} from '$plugins/assistants/utils/formatFileSize';

    /** The files of one upload batch, updated live while they upload. */
    let {files}: { files: IngestFile[] } = $props();

    const {__} = useTranslator();

    const phase = $derived(
        files.some((f) => f.state === 'uploading') ? 'uploading'
            : files.some((f) => f.state === 'done') ? 'done'
                : 'failed'
    );
    /** Whole batch, 0–100; failed files count as finished. */
    const overall = $derived(
        files.reduce((sum, f) => sum + (f.state === 'uploading' ? f.progress : 100), 0) / Math.max(files.length, 1)
    );

    const extensionOf = (name: string): string => name.includes('.') ? name.split('.').pop()!.toLowerCase() : '?';
</script>

<!-- One upload batch as it goes into the knowledge: an overall ring that
     turns into a check, and a row per file with its own progress line.
     The guide's reply to the files follows as its own message. -->
<div class="ingest" data-phase={phase}>
    <p class="head" role="status" aria-live="polite">
        <span class="badge" aria-hidden="true">
            {#if phase === 'uploading'}
                <RadialProgress value={overall} size={16} strokeWidth={2}/>
            {:else if phase === 'done'}
                <span class="pop"><CheckmarkCircle02Icon size="1rem"/></span>
            {:else}
                <Alert02Icon size="1rem"/>
            {/if}
        </span>
        <ShimmerText active={phase === 'uploading'}>{__(`assistants.builder.guide.ingest.${phase}`)}</ShimmerText>
    </p>
    <ul class="files">
        {#each files as file, i (i)}
            <li class="file" data-state={file.state}>
                <img class="file-icon" src={getFileIconSvg(extensionOf(file.name))} alt=""/>
                <div class="file-text">
                    <span class="file-name" title={file.name}>{file.name}</span>
                    <span class="file-meta">
                        {file.state === 'failed' ? __('assistants.builder.guide.ingest.file_failed') : formatFileSize(file.size)}
                    </span>
                    <span class="track" aria-hidden="true">
                        <span class="fill" style:transform="scaleX({file.progress / 100})"></span>
                    </span>
                </div>
                <span class="file-status" aria-hidden="true">
                    {#if file.state === 'done'}
                        <span class="pop"><Tick02Icon size="0.875rem"/></span>
                    {:else if file.state === 'uploading'}
                        {Math.round(file.progress)}%
                    {/if}
                </span>
            </li>
        {/each}
    </ul>
</div>

<style>
    .ingest {
        --pop: cubic-bezier(0.34, 1.56, 0.64, 1);
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        width: 18rem;
        max-width: 100%;
        padding: var(--space-3);
        border-radius: var(--corner-lg);
        background: var(--color-surface-light);
    }

    .head {
        display: flex;
        align-items: center;
        gap: var(--space-1_5);
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }

    .badge {
        display: inline-grid;
        place-items: center;
        width: 1rem;
        height: 1rem;
        color: var(--color-accent-fill);
    }

    [data-phase='failed'] .badge {
        color: var(--color-error);
    }

    .files {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .file {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        min-width: 0;
    }

    .file-icon {
        flex-shrink: 0;
        width: 1.75rem;
        height: 1.75rem;
        object-fit: contain;
        transition: opacity 250ms, filter 250ms;
    }

    .file[data-state='uploading'] .file-icon {
        opacity: 0.55;
    }

    .file[data-state='failed'] .file-icon {
        opacity: 0.4;
        filter: grayscale(1);
    }

    .file-text {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-width: 0;
    }

    .file-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: var(--font-size-xs);
        color: var(--color-text);
    }

    .file-meta {
        font-size: var(--font-size-xxs);
        color: var(--color-text-muted);
    }

    .file[data-state='failed'] .file-meta {
        color: var(--color-error);
    }

    /* Thin progress line under the name; folds away once the file is in. */
    .track {
        display: block;
        height: 2px;
        margin-top: var(--space-1);
        overflow: hidden;
        border-radius: var(--corner-full);
        background: color-mix(in oklch, var(--color-text-muted) 18%, transparent);
        transition: opacity 300ms 250ms, height 300ms 250ms, margin 300ms 250ms;
    }

    .fill {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: var(--color-accent-fill);
        transform-origin: left;
        transition: transform 300ms var(--easing-spring, ease-out);
    }

    .file:not([data-state='uploading']) .track {
        height: 0;
        margin-top: 0;
        opacity: 0;
    }

    .file-status {
        display: inline-grid;
        flex-shrink: 0;
        place-items: center;
        min-width: 2rem;
        justify-items: end;
        font-size: var(--font-size-xxs);
        font-variant-numeric: tabular-nums;
        color: var(--color-text-muted);
    }

    .file[data-state='done'] .file-status {
        color: var(--color-accent-fill);
    }

    /* Checks land with a small overshoot. */
    .pop {
        display: inline-grid;
        animation: ingest-pop 420ms var(--pop) both;
    }

    @keyframes ingest-pop {
        from {
            opacity: 0;
            transform: scale(0.4);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .pop {
            animation: none;
        }

        .track, .fill, .file-icon {
            transition: none;
        }
    }
</style>
