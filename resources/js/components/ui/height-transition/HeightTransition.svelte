<!--
  @component Eases its height to follow its content instead of snapping when the
  content grows or shrinks. It measures the content and sets that as its own
  height, so the change can transition.

  The first measurement is not animated: the height starts unset and only
  follows the content from then on. Its state lives in this component, so a
  fresh instance (e.g. a dialog reopened) never grows from an old height.

  Set `--height-transition-max` on an ancestor to cap it; content past the cap
  is up to the children to scroll.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';

    interface Props {
        children: Snippet;
    }

    let {children}: Props = $props();

    let contentHeight = $state<number | undefined>();
</script>

<div class="height-transition" style:height={contentHeight === undefined ? undefined : `${contentHeight}px`}>
    <div class="height-transition-content" bind:offsetHeight={contentHeight}>
        {@render children()}
    </div>
</div>

<style>
    .height-transition,
    .height-transition-content {
        max-height: var(--height-transition-max, none);
    }

    .height-transition {
        overflow: hidden;
        transition: height var(--duration-extra-fast) var(--easing-out);
    }

    .height-transition-content {
        display: flex;
        flex-direction: column;
    }

    @media (prefers-reduced-motion: reduce) {
        .height-transition {
            transition: none;
        }
    }
</style>
