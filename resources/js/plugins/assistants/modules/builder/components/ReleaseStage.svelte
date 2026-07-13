<script lang="ts">
    import { useBuilderContext } from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import { ReleaseMode } from "$plugins/assistants/types/assistant/ReleaseMode";
    import RadioCardGroup from "$lib/components/ui/radio-card/RadioCardGroup.svelte";
    import RadioCard from "$lib/components/ui/radio-card/RadioCard.svelte";
    import SquareLock02Icon from "$lib/components/ui/icons/iconset/SquareLock02Icon.svelte";
    import CheckmarkBadge01Icon from "$lib/components/ui/icons/iconset/CheckmarkBadge01Icon.svelte";
    import GlobeIcon from "$lib/components/ui/icons/iconset/GlobeIcon.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";


    const {__} = useTranslator()
    const builder = useBuilderContext();

    let currentValue = $derived(builder.draft.releaseStage);

    function update(value: string) {
        builder.set('releaseStage', value as ReleaseMode);
    }
</script>

<div class="input-container" class:renderBlock={true}>

    <p class="u-label">{__('assistants.builder.publish.release_stage.title')}</p>

    <RadioCardGroup class="release-stage-group" value={currentValue} onChange={update} name="release-stage">
        <RadioCard value={ReleaseMode.PRIVATE}>
            <span class="stage-label"><SquareLock02Icon size="1em" /> {__('assistants.builder.publish.release_stage.private_label')}</span>
            <span class="stage-description">{__('assistants.builder.publish.release_stage.private_description')}</span>
        </RadioCard>

        <RadioCard value={ReleaseMode.ORGANIZATIONAL}>
            <span class="stage-label"><CheckmarkBadge01Icon size="1em" /> {__('assistants.builder.publish.release_stage.organizational_label')}</span>
            <span class="stage-description">{__('assistants.builder.publish.release_stage.organizational_description')}</span>
        </RadioCard>

        <RadioCard value={ReleaseMode.FEDERATED}>
            <span class="stage-label"><GlobeIcon size="1em" /> {__('assistants.builder.publish.release_stage.federated_label')}</span>
            <span class="stage-description">{__('assistants.builder.publish.release_stage.federated_description')}</span>
        </RadioCard>
    </RadioCardGroup>
</div>


<style>
    .u-label {
        font-weight: var(--font-weight-semibold);
    }

    /* The shared RadioCard body is single-line/ellipsis by default; the
       release-stage options carry a label + explanatory description, so
       allow two lines here. Fully :global because both the group div and
       the body div belong to the child RadioCardGroup/RadioCard components
       (the scoped .release-stage-group class alone never matches). */
    :global(.release-stage-group .radio-card-body) {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-0_5, 2px);
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
    }

    .stage-label {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        font-weight: var(--font-weight-medium, 500);
    }

    .stage-description {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
</style>
