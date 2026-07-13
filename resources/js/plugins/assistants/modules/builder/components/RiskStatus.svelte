<script lang="ts">
    import CheckmarkCircle02Icon from "$lib/components/ui/icons/iconset/CheckmarkCircle02Icon.svelte";
    import InformationCircleIcon from "$lib/components/ui/icons/iconset/InformationCircleIcon.svelte";
    import Alert01Icon from "$lib/components/ui/icons/iconset/Alert01Icon.svelte";
    import { RiskLevel } from "$plugins/assistants/types/assistant/RiskLevel";
    import StatusPill from "$plugins/assistants/components/status/StatusPill.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    const {__} = useTranslator();

    let {
        level
    } = $props<{
        level: RiskLevel
    }>();

    const config = $derived<
        {label: string; icon: typeof CheckmarkCircle02Icon; tone: "info" | "safe" | "warning" | "error"} | null
    >(
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
    <StatusPill label={config.label} icon={config.icon} tone={config.tone} />
{/if}
