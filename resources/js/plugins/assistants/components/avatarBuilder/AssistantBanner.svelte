<script lang="ts">
    import type {AssistantAvatar} from "$plugins/assistants/types/assistant/AssistantAvatar";

    let{
        assistantAvatar
    } = $props <{
        assistantAvatar: AssistantAvatar
    }>();
</script>


<div class="banner-container"
     style={assistantAvatar.iconCss}
>
    <span class="symbol"
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
    /* Oversized glyph reads as a decorative cover watermark. */
    .symbol {
        font-size: 3.5rem;
        line-height: 1;
    }
</style>
