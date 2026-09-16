<script lang="ts">
    import { useBuilderContext } from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import { ReleaseMode } from "$plugins/assistants/types/assistant/ReleaseMode";
    import RadioSwitch from "$plugins/assistants/components/radioSwitch/RadioSwitch.svelte";
    import RadioOption from "$plugins/assistants/components/radioSwitch/RadioOption.svelte";
    import TaskEdit01Icon from "$lib/components/ui/icons/iconset/TaskEdit01Icon.svelte";
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

    <RadioSwitch value={currentValue} onchange={update} name="release-stage">
        <RadioOption value={ReleaseMode.DRAFT}
                     icon={TaskEdit01Icon}
                     label={__('assistants.builder.publish.release_stage.draft_label')}
                     description={__('assistants.builder.publish.release_stage.draft_description')} />

        <RadioOption value={ReleaseMode.PRIVATE}
                     icon={SquareLock02Icon}
                     label={__('assistants.builder.publish.release_stage.private_label')}
                     description={__('assistants.builder.publish.release_stage.private_description')} />

        <RadioOption value={ReleaseMode.ORGANIZATIONAL}
                     icon={CheckmarkBadge01Icon}
                     label={__('assistants.builder.publish.release_stage.organizational_label')}
                     description={__('assistants.builder.publish.release_stage.organizational_description')} />

        <RadioOption value={ReleaseMode.FEDERATED}
                     icon={GlobeIcon}
                     label={__('assistants.builder.publish.release_stage.federated_label')}
                     description={__('assistants.builder.publish.release_stage.federated_description')} />
    </RadioSwitch>
</div>


<style>
    .u-label {
        font-weight: var(--font-weight-semibold);
    }
</style>
