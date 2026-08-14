<script lang="ts">

    import type {IconComponent} from '$lib/components/ui/icons';
    import type {ValidationState} from "$lib/types/enums/ValidationState";

    let {
        label,
        icon,
        status,
        render = "filletEdge",
        type = "info",
        size = "medium",
        fullWidth = false
    } = $props<{
        label: string;
        icon?: IconComponent;
        status?: string;
        render?: "roundEdge" | "filletEdge";
        size?: "small" | "medium" | "large";
        type?: ValidationState;
        fullWidth?: boolean;
    }>();
</script>

<div class="status-card"
     class:roundEdge={render === "roundEdge"}
     class:filletEdge={render === "filletEdge"}
     class:typeInfo={type === "info"}
     class:typeWarning={type === "warning"}
     class:typeError={type === "error"}
     class:typeSafe={type === "safe"}
     class:typeUnknown={type === "unknown"}
     class:fullWidth={fullWidth}
     class:withStatus={status}
     class:sizeSmall={size === "small"}
     class:sizeMedium={size === "medium"}
     class:sizeLarge={size === "large"}
>
    {#if size === "large"}
        {#if icon}
            {@const IconCmp = icon}
            <span class="icon"><IconCmp size="1em" /></span>
        {/if}
    {/if}
    <div class="content">

        <div class="header">
            {#if icon && size !== "large"}
                {@const IconCmp = icon}
                <span class="icon"><IconCmp size="1em" /></span>
            {/if}
            <p class="label label-xs">{label}</p>
        </div>
        {#if status}
            <p class="status">{status}</p>
        {/if}
    </div>
</div>

<style>
    .status-card{
        display: flex;
        flex-direction: row;
        gap: var(--space-2);
        height: fit-content;
        width: max-content;
        max-width: 100%;
        justify-content: space-between;
        padding: 0 var(--space-2);
        align-items: center;
        font-size: var(--font-size-xs);
        border: none;
    }
    .status-card.sizeSmall {
        height: 1.25rem;
        padding: 0 var(--space-1);
        gap: var(--space-1);
    }
    .status-card.sizeSmall .content{
        padding: var(--space-1);
        gap: 0;
    }
    .status-card.sizeSmall .header{
        gap: var(--space-1);
        align-items: center;
        line-height: 1;
    }
    .status-card.sizeSmall .label{
        font-size: var(--font-size-xxs);
        line-height: 1;
        display: flex;
        align-items: center;
    }
    .status-card.sizeSmall .icon{
        font-size: var(--font-size-xs);
        line-height: 1;
        display: flex;
        align-items: center;
    }

    .status-card.sizeLarge{
        height: 4rem;
        min-width: 8rem;
        width: 100%;
        padding: 0 var(--space-4);
        display: flex;
        flex-direction: row;
    }
    .status-card.sizeLarge .content {
        gap: 0;
    }
    .status-card.sizeLarge .label{
        font-size: var(--font-size-xxs);
    }
    .status-card.sizeLarge .icon{
        font-size: var(--font-size-lg);
    }


    .fullWidth{
        width: 100%;
    }
    .withStatus .status{
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }

    /* Soft tinted pills: a low-alpha wash of the semantic status color with a
       matching hairline, echoing the sidebar's accent-100 highlight language. */
    .typeInfo{
        background: color-mix(in oklch, var(--color-info) 12%, transparent);
        color: color-mix(in oklch, var(--color-info) 75%, var(--color-text));
    }
    .typeWarning{
        background: color-mix(in oklch, var(--color-warning) 14%, transparent);
        color: color-mix(in oklch, var(--color-warning) 70%, var(--color-text));
    }
    .typeError{
        background: color-mix(in oklch, var(--color-error) 12%, transparent);
        color: color-mix(in oklch, var(--color-error) 75%, var(--color-text));
    }
    .typeSafe{
        background: color-mix(in oklch, var(--color-success) 12%, transparent);
        color: color-mix(in oklch, var(--color-success) 70%, var(--color-text));
    }
    .typeUnknown{
        background: var(--color-hover);
        color: var(--color-text-muted);
    }

    /* Exclude small pills so this never competes with `.sizeSmall`'s height —
       a small round pill then has exactly one height rule, no cascade race. */
    .roundEdge:not(.sizeSmall){
        height: 2rem;
    }
    .roundEdge{
        border-radius: var(--corner-full);
    }
    .filletEdge{
        border-radius: var(--corner-sm);
    }
    .content{
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        gap: var(--space-1);
        padding: var(--space-2);
        width: 100%;
        line-height: 1.2;
    }
    .content p{
        padding: 0;
        margin: 0;
    }
    .header{
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
    }
    .icon{
        font-size: 1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 0;
        flex-shrink: 0;
    }
</style>
