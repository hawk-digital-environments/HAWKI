<script lang="ts">
    import SquareLock02Icon from "$lib/components/ui/icons/iconset/SquareLock02Icon.svelte";
    import CheckmarkBadge01Icon from "$lib/components/ui/icons/iconset/CheckmarkBadge01Icon.svelte";
    import GlobeIcon from "$lib/components/ui/icons/iconset/GlobeIcon.svelte";
    import { ReleaseMode } from "$plugins/assistants/types/assistant/ReleaseMode";
    import StatusPill from "$plugins/assistants/components/status/StatusPill.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";

    const {__} = useTranslator();

    let {
        stage
    } = $props<{
        stage: ReleaseMode
    }>();

    const config = $derived<
        {label: string; icon: typeof SquareLock02Icon; tone: "info" | "safe" | "warning" | "error"} | null
    >(
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
    <StatusPill label={config.label} icon={config.icon} tone={config.tone} />
{/if}
