<script lang="ts">
    import type { UploadFile } from "$lib/plugins/assistants/types/UploadFile";
    import RadialProgress from "$lib/components/ui/radial-progress/RadialProgress.svelte";
    import CheckmarkCircleIcon from "$lib/components/ui/icons/iconset/CheckmarkCircleIcon.svelte";
    import CircleXIcon from "$lib/components/ui/icons/iconset/CircleXIcon.svelte";

    interface Props {
        /** Active drag state. `idle` hides the valid/invalid badges. */
        status?: "idle" | "valid" | "invalid";
        /** Files being uploaded (or already attached) to render progress for. */
        files?: UploadFile[];
    }

    const { status = "idle", files = [] }: Props = $props();

    /**
     * Map the `status` prop onto the CSS class the stylesheet keys off
     * (`drag-valid` / `drag-invalid`).
     *
     * Unify with hawki frontend function `updateFileStatus` when migrating to
     * hawki frontend. Ported from HAWKI/public/js/attachment_handler.js:98 —
     * the DOM `classList.add('status-...')` toggling is expressed here as a
     * derived class string.
     */
    const overlayClass = $derived(
        [
            "drag-drop-overlay",
            status === "valid" ? "drag-valid" : "",
            status === "invalid" ? "drag-invalid" : "",
        ]
            .filter(Boolean)
            .join(" "),
    );

    /** Files worth showing a progress chip for (currently uploading or failed). */
    const activeUploads = $derived(
        files.filter((f) => f.status === "uploading" || f.status === "error"),
    );
</script>

<div class={overlayClass}>
    <div class="drag-drop-box">
        <div class="drag-drop-content">
            <div class="icon">
                <img src="/img/upload.png" alt="Upload">
            </div>
            <div class="drag-drop-text">
                <h4>Add Files</h4>
                <p class="drag-drop-desc">Drop files here to add them to the conversation.</p>
                <p class="drag-status-msg" aria-live="polite"></p>
            </div>
        </div>
        <div class="drag-drop-status" aria-live="polite">
            <div class="drag-status-valid">
                <CheckmarkCircleIcon size="1rem" />
                <span>File type supported</span>
            </div>
            <div class="drag-status-invalid">
                <CircleXIcon size="1rem" />
                <span>File type not supported</span>
            </div>
        </div>

        {#if activeUploads.length}
            <ul class="upload-progress-list" aria-live="polite">
                {#each activeUploads as file (file.name)}
                    <li class="upload-progress-item status-{file.status ?? 'pending'}">
                        <RadialProgress value={file.progress ?? 0} />
                        <span class="upload-progress-name">{file.name}</span>
                        {#if file.status === "error" && file.error}
                            <span class="upload-progress-error">{file.error}</span>
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}
    </div>
</div>


<style>

    .drag-drop-overlay {
        /*display: none;*/
        position: absolute;
        top: 0;
        bottom: 0;
        left: 0;
        right: 0;
        justify-content: center;
        align-items: center;
        z-index: 5;
        background-color: var(--color-surface-raised);
        backdrop-filter: blur(3px);
        -webkit-backdrop-filter: blur(3px);
        border-radius: var(--corner-md);
    }

    .drag-drop-overlay .drag-drop-limits {
        font-size: var(--font-size-sm);
    }

    .drag-drop-box {
        padding: 0 2rem;
        background: var(--color-surface-raised);
        border: 2px dashed var(--color-accent-500);;
        border-radius: 1rem;
        width: 100%;
        height: 100%;
        min-height: 5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        overflow: hidden;
        transition: border-color 0.2s ease, background-color 0.2s ease;
        position: relative;
    }

    /* Drag-over valid state */
    .drag-drop-overlay.drag-valid .drag-drop-box {
        border-color: #22c55e;
        background-color: rgba(34, 197, 94, 0.08);
    }

    /* Drag-over invalid state */
    .drag-drop-overlay.drag-invalid .drag-drop-box {
        border-color: #ef4444;
        background-color: rgba(239, 68, 68, 0.08);
    }

    /* Status badge: hidden by default, shown when a state is set */
    .drag-drop-status {
        display: none;
        position: absolute;
        top: 0.6rem;
        right: 0.8rem;
    }

    .drag-drop-overlay.drag-valid .drag-drop-status,
    .drag-drop-overlay.drag-invalid .drag-drop-status {
        display: flex;
    }

    .drag-status-valid,
    .drag-status-invalid {
        display: none;
        align-items: center;
        gap: 0.4rem;
        font-size: var(--font-size-sm);
        font-weight: 600;
        padding: 0.25rem 0.6rem;
        border-radius: 2rem;
    }

    .drag-drop-overlay.drag-valid .drag-status-valid {
        display: flex;
        color: #16a34a;
        background-color: rgba(34, 197, 94, 0.15);
    }

    .drag-drop-overlay.drag-invalid .drag-status-invalid {
        display: flex;
        color: #dc2626;
        background-color: rgba(239, 68, 68, 0.15);
    }

    /* :global() because the svg is rendered by the child icon component
     * (CheckmarkCircleIcon / CircleXIcon), so a scoped `svg` selector would
     * neither match at runtime nor satisfy svelte-check's static analysis. */
    .drag-status-valid :global(svg),
    .drag-status-invalid :global(svg) {
        width: 1rem;
        height: 1rem;
        stroke-width: 2.5;
        flex-shrink: 0;
    }

    .drag-status-valid :global(svg) {
        stroke: #16a34a;
    }

    .drag-status-invalid :global(svg) {
        stroke: #dc2626;
    }

    .drag-drop-content{
        display: grid;
        grid-template-columns: auto 1fr;
        text-align: center;
        height: 100%;
        width: fit-content;
        justify-self: center;
    }

    .drag-drop-box .icon{
        display: flex;
        align-items: center;
    }
    @media (max-width: 800px){
        .drag-drop-box .icon{
            display: none;
        }
    }

    .drag-drop-box .icon img {
        width: auto;
        height: 6rem;
    }

    .drag-drop-box h4 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
        /* color: #1e3a8a; */
        color: var(--color-accent-500);
    }

    .drag-drop-box p {
        color: var(--color-text);
        line-height: 1.4;
        margin: 0.5rem 0 0 0;
        font-size: var(--font-size-xs);
    }

    .drag-drop-text{
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: fit-content;
        padding: 0 2rem;
    }

    /* -- Drag status message (shown instead of limits/desc during drag) -- */
    .drag-status-msg {
        display: none; /* toggled by JS */
        margin: 0.4rem 0 0 0;
        font-size: var(--font-size-xs);
        font-weight: 500;
        line-height: 1.4;
        text-align: center;
        max-width: 28rem;
    }

    .drag-drop-overlay.drag-valid .drag-status-msg,
    .drag-drop-overlay.drag-valid h4 {
        color: #16a34a;
    }

    .drag-drop-overlay.drag-invalid .drag-status-msg,
    .drag-drop-overlay.drag-invalid h4 {
        color: #dc2626;
    }

    /* -- Per-file upload progress list -- */
    .upload-progress-list {
        list-style: none;
        margin: var(--space-2) 0 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    .upload-progress-item {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }

    .upload-progress-item.status-error {
        color: #dc2626;
    }

    .upload-progress-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .upload-progress-error {
        margin-left: auto;
        font-weight: var(--font-weight-medium);
    }

</style>
