<script lang="ts">
    import SquareLock02Icon from "$lib/components/ui/icons/iconset/SquareLock02Icon.svelte";
    import CheckmarkBadge01Icon from "$lib/components/ui/icons/iconset/CheckmarkBadge01Icon.svelte";
    import GlobeIcon from "$lib/components/ui/icons/iconset/GlobeIcon.svelte";
    import { ReleaseMode } from "$lib/plugins/assistants/types/assistant/ReleaseMode";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";

    const {__} = useTranslator();

    let {
        stage
    } = $props<{
        stage: ReleaseMode
    }>();

    const config = $derived(
        stage === ReleaseMode.PRIVATE
            ? { label: __('assistants.release_stage.private'), icon: SquareLock02Icon, tone: "info" }
        : stage === ReleaseMode.ORGANIZATIONAL
            ? { label: __('assistants.release_stage.organizational'), icon: CheckmarkBadge01Icon, tone: "safe" }
        : stage === ReleaseMode.FEDERATED
            ? { label: __('assistants.release_stage.federated'), icon: GlobeIcon, tone: "safe" }
        : null
    );
</script>

{#if config}
    {@const StageIcon = config.icon}
    <span class="release-pill" class:toneInfo={config.tone === "info"} class:toneSafe={config.tone === "safe"}>
        <span class="icon"><StageIcon size="1em" /></span>
        <span class="label">{config.label}</span>
    </span>
{/if}

<style>
    /* Self-contained, content-hugging pill. Height comes only from the padding
       and the single line of text — there is no fixed height to fight over. */
    .release-pill {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        width: fit-content;
        max-width: 100%;
        padding: var(--space-1) var(--space-2);
        border: none;
        border-radius: var(--corner-full);
        font-size: var(--font-size-xxs);
        line-height: 1;
        white-space: nowrap;
    }
    .icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: var(--font-size-xs);
        line-height: 0;
    }
    .label {
        line-height: 1;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .toneInfo {
        background: color-mix(in oklch, var(--color-info) 12%, transparent);
        color: color-mix(in oklch, var(--color-info) 75%, var(--color-text));
    }
    .toneSafe {
        background: color-mix(in oklch, var(--color-success) 12%, transparent);
        color: color-mix(in oklch, var(--color-success) 70%, var(--color-text));
    }
</style>
