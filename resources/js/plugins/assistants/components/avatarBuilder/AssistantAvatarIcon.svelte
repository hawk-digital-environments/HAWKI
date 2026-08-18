<script lang="ts">
    import type {AssistantAvatar} from "$lib/plugins/assistants/types/assistant/AssistantAvatar";

    let{
        assistantAvatar,
        type="roundEdge",
        size="medium"
    } = $props <{
        type?: "roundEdge" | "round"
        size?: "small" | "medium" | "large"
        assistantAvatar: AssistantAvatar
    }>();

</script>

<div class="icon-container"
     class:roundEdge={type === "roundEdge"}
     class:round={type === "round"}
     class:sizeSmall={size === "small"}
     class:sizeMedium={size === "medium"}
     class:sizeLarge={size === "large"}
     style={assistantAvatar.iconCss}
>
        <span class="symbol"
              style:color="oklch(100% 0 0)"
        >
            {assistantAvatar.name}
        </span>
</div>


<style>
    .icon-container {
        display: flex;
        align-items: center;
        justify-content: center;
        aspect-ratio: 1 / 1;
    }
    .icon-container.sizeSmall{
        width: 3rem;
        height: 3rem;
    }
    .icon-container.sizeMedium{
        width: 5rem;
        height: 5rem;
    }
    .icon-container.sizeLarge{
        width: 7rem;
        height: 7rem;
    }
    .icon-container.roundEdge{
        border-radius: var(--corner-md);
    }
    .icon-container.round{
        border-radius: 50%;
    }

    .icon-container .symbol {
        line-height: 1;
    }
    .sizeSmall .symbol{
        font-size: 1.5rem;
    }
    .sizeMedium .symbol{
        font-size: 2.5rem;
    }
    .sizeLarge .symbol{
        font-size: 3.5rem;
    }


</style>
