<script lang="ts">
    import CheckmarkCircle02Icon from "$lib/components/ui/icons/iconset/CheckmarkCircle02Icon.svelte";
    import InformationCircleIcon from "$lib/components/ui/icons/iconset/InformationCircleIcon.svelte";
    import Alert01Icon from "$lib/components/ui/icons/iconset/Alert01Icon.svelte";
    import { RiskLevel } from "$lib/plugins/assistants/types/assistant/RiskLevel";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    const {__} = useTranslator();

    let {
        level
    } = $props<{
        level: RiskLevel
    }>();

    const config = $derived(
        level === RiskLevel.LOW
            ? { label: __('assistants.risk.low'), icon: CheckmarkCircle02Icon, tone: "safe" }
        : level === RiskLevel.MEDIUM
            ? { label: __('assistants.risk.medium'), icon: InformationCircleIcon, tone: "warning" }
        : level === RiskLevel.HIGH
            ? { label: __('assistants.risk.high'), icon: Alert01Icon, tone: "error" }
        : null
    );
</script>

{#if config}
    {@const RiskIcon = config.icon}
    <span
        class="risk-pill"
        class:toneSafe={config.tone === "safe"}
        class:toneWarning={config.tone === "warning"}
        class:toneError={config.tone === "error"}
    >
        <span class="icon"><RiskIcon size="1em" /></span>
        <span class="label">{config.label}</span>
    </span>
{/if}

<style>
    /* Content-hugging pill, matched to ReleaseStageStatus so the two sit
       together on the same badge row. */
    .risk-pill {
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

    .toneSafe {
        background: color-mix(in oklch, var(--color-success) 12%, transparent);
        color: color-mix(in oklch, var(--color-success) 70%, var(--color-text));
    }
    .toneWarning {
        background: color-mix(in oklch, var(--color-warning) 14%, transparent);
        color: color-mix(in oklch, var(--color-warning) 78%, var(--color-text));
    }
    .toneError {
        background: color-mix(in oklch, var(--color-error) 12%, transparent);
        color: color-mix(in oklch, var(--color-error) 72%, var(--color-text));
    }
</style>
