<script lang="ts">
    import type { AssistantAvatar } from '$plugins/assistants/types/assistant/AssistantAvatar';

    let { assistantAvatar, fade = false } = $props<{
        assistantAvatar: AssistantAvatar;
        /** Dissolve the banner into the surface below it (store card, detail page). */
        fade?: boolean;
    }>();
</script>

<div
    class="banner-container"
    class:fade
    style={assistantAvatar.iconCss}
>
    <span
        class="symbol"
        style:color="oklch(100% 0 0)"
    >
        {assistantAvatar.name}
    </span>
</div>

<style>
    .banner-container {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 6rem;
        width: 100%;
        overflow: hidden;
    }
    /* Eased (ease-in-out) fade instead of a linear ramp: a linear mask
       leaves visible "corners" where the ramp starts and ends. */
    .fade {
        mask-image: linear-gradient(
            to bottom,
            rgb(0 0 0 / 1) 20%,
            rgb(0 0 0 / 0.987) 26.5%,
            rgb(0 0 0 / 0.951) 32.4%,
            rgb(0 0 0 / 0.896) 38%,
            rgb(0 0 0 / 0.825) 43.2%,
            rgb(0 0 0 / 0.741) 48.2%,
            rgb(0 0 0 / 0.648) 53%,
            rgb(0 0 0 / 0.55) 57.7%,
            rgb(0 0 0 / 0.45) 62.3%,
            rgb(0 0 0 / 0.352) 67%,
            rgb(0 0 0 / 0.259) 71.8%,
            rgb(0 0 0 / 0.175) 76.8%,
            rgb(0 0 0 / 0.104) 82%,
            rgb(0 0 0 / 0.049) 87.6%,
            rgb(0 0 0 / 0.013) 93.5%,
            rgb(0 0 0 / 0) 100%
        );
    }
    /* Oversized glyph reads as a decorative cover watermark. */
    .symbol {
        font-size: 3.5rem;
        line-height: 1;
    }
</style>
