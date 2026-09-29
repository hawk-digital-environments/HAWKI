<!--
  @component Wraps a builder field and plays a reveal when the AI guide fills
  that field (`builder.markAiFilled`): the field materialises left to right
  behind a soft edge with a faint blue sheen. The reveal waits
  until the field is on screen, so a field filled on another step plays when
  that step is opened, and it plays once per fill. Several fields filled
  together cascade top to bottom.

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

    interface Props {
        field: keyof Assistant;
        children: Snippet;
    }

    const {field, children}: Props = $props();
    const builder = useBuilderContext();

    /** Length of the reveal, excluding its cascade delay (ms). */
    const DURATION = 900;
    const MAX_DELAY = 480;

    let host = $state<HTMLElement | null>(null);
    let run = $state(0);
    let delay = $state(0);
    let active = $state(false);
    let hideTimer: ReturnType<typeof setTimeout> | undefined;

    onDestroy(() => clearTimeout(hideTimer));

    $effect(() => {
        const fill = builder.aiFills[field];
        if (!host || fill === undefined) return;

        const el = host;
        const observer = new IntersectionObserver((entries) => {
            const entry = entries.find((e) => e.isIntersecting);
            if (!entry) return;
            observer.disconnect();
            if (!builder.claimAiFill(field)) return;
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

<div class="ai-fill" bind:this={host}>
    <!-- Not keyed: the field itself must stay mounted (focus, input state);
         re-adding `revealing` restarts the animation. -->
    <div class="content" class:revealing={active} style:--reveal-delay="{delay}ms">
        {@render children()}
    </div>
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
        border-radius: var(--corner-md);
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
