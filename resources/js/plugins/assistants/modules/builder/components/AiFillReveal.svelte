<!--
  @component Wraps a builder field and plays a reveal when the AI guide fills
  that field (`builder.markAiFilled`): the field materialises left to right
  behind a soft edge with a faint blue sheen. The reveal waits
  until the field is on screen, so a field filled on another step plays when
  that step is opened, and it plays once per fill. Several fields filled
  together cascade top to bottom. It also scrolls the field into view on
  `builder.requestScrollTo(field)`.

  While a guide turn may write the field (`builder.isAiBusy`), it is locked
  and steps back while a small accent light travels round its outline
  (`LoadingBeam`, which reads its geometry from the `--ai-fill-*`
  properties below). The outline takes the wrapper's box; `fit` shrinks the
  wrapper to its content (e.g. the lone "Add tag" button), `--ai-fill-radius`
  matches its corners, `--ai-fill-ring-width` thickens the light and
  `--ai-fill-ring-offset` draws it outside the field, for a field whose own
  fill would hide it (the avatar).

  A field with motion of its own (the avatar) passes `onreveal` and plays
  that instead of the sweep; under reduced motion it gets the same fade as
  every other field.

  @example
  ```svelte
  <AiFillReveal field="systemPrompt">
      <Textarea …/>
  </AiFillReveal>
  ```
-->
<script lang="ts">
    import {onDestroy, type Snippet} from 'svelte';
    import type {Assistant} from '$plugins/assistants/types/assistant/Assistant';
    import {useBuilderContext} from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import {useReducedMotion} from '$lib/utils/transitions/reducedMotion.svelte.js';
    import LoadingBeam from '$lib/components/ui/loading-beam/LoadingBeam.svelte';

    interface Props {
        field: keyof Assistant;
        /** Replaces the sweep: called when the reveal is due, nothing is drawn. */
        onreveal?: () => void;
        /** Shrink the wrapper (and the skeleton) to its content's width. */
        fit?: boolean;
        /** Also busy for an AI write of the field's own (e.g. a suggestion). */
        busy?: boolean;
        children: Snippet;
    }

    const {field, onreveal, fit = false, busy: ownBusy = false, children}: Props = $props();
    const builder = useBuilderContext();
    const reducedMotion = useReducedMotion();

    let busy = $derived(ownBusy || builder.isAiBusy(field));

    /** Length of the reveal, excluding its cascade delay (ms). */
    const DURATION = 900;
    const MAX_DELAY = 480;

    let host = $state<HTMLElement | null>(null);
    let run = $state(0);
    let delay = $state(0);
    let active = $state(false);
    let hideTimer: ReturnType<typeof setTimeout> | undefined;

    onDestroy(() => clearTimeout(hideTimer));

    // Bring the field into view when asked to (see `builder.requestScrollTo`).
    // A frame's delay lets a freshly opened step finish laying out first.
    $effect(() => {
        if (!host || builder.scrollRequest !== field) return;
        builder.claimScrollRequest(field);
        const el = host;
        requestAnimationFrame(() => el.scrollIntoView({
            behavior: reducedMotion.current ? 'auto' : 'smooth',
            block: 'center',
        }));
    });

    $effect(() => {
        const fill = builder.aiFills[field];
        if (!host || fill === undefined) return;

        const el = host;
        const observer = new IntersectionObserver((entries) => {
            const entry = entries.find((e) => e.isIntersecting);
            if (!entry) return;
            observer.disconnect();
            if (!builder.claimAiFill(field)) return;
            if (onreveal && !reducedMotion.current) {
                onreveal();
                return;
            }
            // Lower fields start a little later, so a batch reads as one wave.
            const top = entry.boundingClientRect.top - (entry.rootBounds?.top ?? 0);
            delay = Math.round(Math.min(MAX_DELAY, Math.max(0, top) * 0.6));
            run++;
            // Off for a frame first, so a fill landing mid-reveal restarts it.
            active = false;
            requestAnimationFrame(() => active = true);
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => active = false, delay + DURATION);
        }, {threshold: 0.2});
        observer.observe(el);

        return () => observer.disconnect();
    });
</script>

<div class="ai-fill" class:fit class:busy bind:this={host}>
    <!-- Not keyed: the field itself must stay mounted (focus, input state);
         re-adding `revealing` restarts the animation. -->
    <div class="content" class:revealing={active} class:busy style:--reveal-delay="{delay}ms"
         inert={busy} aria-busy={busy}>
        {@render children()}
    </div>
    {#if busy}
        <LoadingBeam/>
    {/if}
    {#if active}
        {#key run}
            <span class="sheen" style:--reveal-delay="{delay}ms" aria-hidden="true"></span>
        {/key}
    {/if}
</div>

<style>
    .ai-fill {
        position: relative;
        --reveal-ease: cubic-bezier(0.22, 1, 0.36, 1);
        /* The busy light (`LoadingBeam`) reads its geometry from these; the
           `--ai-fill-*` names stay the field-facing knobs (see header). */
        --loading-beam-radius: var(--ai-fill-radius, var(--corner-md));
        --loading-beam-thickness: var(--ai-fill-ring-width, 1px);
        --loading-beam-offset: var(--ai-fill-ring-offset, 0px);
    }

    .ai-fill.fit {
        width: fit-content;
        max-width: 100%;
    }

    /* The field materialises left to right: a mask 2.5× the field's width,
       opaque on its left part and near-transparent on its right, slides
       across so the soft edge passes over the field once. */
    .content.revealing {
        mask-image: linear-gradient(90deg, #000 42%, rgb(0 0 0 / 0.08) 58%);
        mask-size: 250% 100%;
        mask-repeat: no-repeat;
        animation: reveal-unmask 900ms var(--reveal-ease) var(--reveal-delay) both;
    }

    /* A faint blue sheen riding the reveal edge, on the same track. */
    .sheen {
        position: absolute;
        inset: 0;
        border-radius: var(--ai-fill-radius, var(--corner-md));
        background: linear-gradient(90deg, transparent 40%, var(--color-accent-200) 50%, transparent 60%) no-repeat;
        background-size: 250% 100%;
        opacity: 0.35;
        pointer-events: none;
        animation: reveal-sheen 900ms var(--reveal-ease) var(--reveal-delay) both;
    }

    @keyframes reveal-unmask {
        from { mask-position: 100% 0; }
        to { mask-position: 0% 0; }
    }

    @keyframes reveal-sheen {
        from { background-position: 100% 0; opacity: 0.35; }
        80% { opacity: 0.35; }
        to { background-position: 0% 0; opacity: 0; }
    }

    /* In use by the builder: the field stays readable but steps back, and
       a small accent light travels round its outline. */
    .content {
        transition: opacity 300ms ease-out;
    }

    .content.busy {
        opacity: 0.6;
    }

    /* The same cursor as a disabled control. Set on every descendant too:
       browsers still take the cursor from the control under the pointer
       (text, pointer, …) even though it is inert. */
    .ai-fill.busy,
    .ai-fill.busy :global(*) {
        cursor: not-allowed;
    }

    @media (prefers-reduced-motion: reduce) {
        .content.revealing {
            mask-image: none;
            animation: reveal-fade 300ms ease-out var(--reveal-delay) both;
        }

        .sheen {
            display: none;
        }

        @keyframes reveal-fade {
            from { opacity: 0.4; }
            to { opacity: 1; }
        }
    }
</style>
