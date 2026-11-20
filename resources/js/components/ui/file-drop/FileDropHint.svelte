<!--
  @component "Drop files here" hint shown while files are dragged over a drop
  target: a small stack of pages that springs open into a fan, with a label
  sliding up beneath it. Mount it only while a drag is active so the
  entrance plays on every drag; it fills its positioned parent.

  With `blocked` the pages stay stacked instead of fanning out and the label
  explains why nothing can be dropped.

  @example
  ```svelte
  {#if isDragging}
      <FileDropHint label={__('chat.composer.fileDrop.dropLabel')}/>
  {/if}
  ```
-->
<script lang="ts">
    interface Props {
        label: string;
        /** Drop not possible right now: no fan, `label` says why. */
        blocked?: boolean;
    }

    const {label, blocked = false}: Props = $props();
</script>

<div class="file-drop-hint" class:blocked role="status">
    <div class="file-drop-fan" aria-hidden="true">
        <span class="file-drop-page file-drop-page--3"></span>
        <span class="file-drop-page file-drop-page--2"></span>
        <span class="file-drop-page file-drop-page--1"></span>
        <span class="file-drop-page file-drop-page--0"></span>
    </div>
    <span class="file-drop-label">{label}</span>
</div>

<style>
    .file-drop-hint {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: var(--space-3, calc(0.25rem * 3));
        color: color-mix(in oklch, var(--color-text-muted) 65%, transparent);
        font-weight: 500;
        text-align: center;
        pointer-events: none;
    }

    .file-drop-label {
        max-width: 22rem;
        animation: file-drop-label-in var(--duration-fast, 300ms) var(--easing-spring) both;
        animation-delay: 80ms;
    }

    /* ── Springy fan of pages ─────────────────────────────────────────── */

    .file-drop-fan {
        position: relative;
        width: 2.25rem;
        height: 2.75rem;
    }

    .file-drop-page {
        position: absolute;
        inset: 0;
        border-radius: calc(var(--corner-md) * 0.75);
        border: 1.5px solid var(--color-border);
        background-color: color-mix(in oklch, var(--color-surface-raised) 96%, var(--color-text-muted));
        box-shadow: 0 2px 6px color-mix(in oklch, var(--color-text-muted) 12%, transparent);
        transform-origin: bottom center;
        /* Overshoot easing so each page springs slightly past its resting
           angle and settles back — a bouncier feel than the token spring. */
        animation: file-drop-page-fan 560ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }

    /* Each page settles at its own angle/offset, staggered for a cascading
       "fan" feel. --fan-rot is the resting rotation the spring lands on. */
    .file-drop-page--0 {
        --fan-rot: 0deg;
        --fan-x: 0;
        --fan-y: 0;
        animation-delay: 40ms;
    }

    .file-drop-page--1 {
        --fan-rot: 13deg;
        --fan-x: 0.32rem;
        --fan-y: -0.1rem;
        animation-delay: 80ms;
    }

    .file-drop-page--2 {
        --fan-rot: 26deg;
        --fan-x: 0.6rem;
        --fan-y: -0.18rem;
        animation-delay: 120ms;
    }

    .file-drop-page--3 {
        --fan-rot: 39deg;
        --fan-x: 0.82rem;
        --fan-y: -0.22rem;
        animation-delay: 160ms;
    }

    /* Blocked: the pages rise as a closed stack and never fan out. */
    .blocked .file-drop-page {
        --fan-rot: 0deg;
        --fan-x: 0;
        --fan-y: 0;
    }

    @keyframes file-drop-page-fan {
        from {
            opacity: 0;
            transform: translate(0, 0.5rem) rotate(0deg) scale(0.7);
        }

        to {
            opacity: 1;
            transform: translate(var(--fan-x), var(--fan-y)) rotate(var(--fan-rot)) scale(1);
        }
    }

    @keyframes file-drop-label-in {
        from {
            opacity: 0;
            transform: translateY(0.5rem);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .file-drop-page, .file-drop-label {
            animation: none;
            opacity: 1;
        }

        .file-drop-page {
            transform: translate(var(--fan-x), var(--fan-y)) rotate(var(--fan-rot));
        }
    }
</style>
