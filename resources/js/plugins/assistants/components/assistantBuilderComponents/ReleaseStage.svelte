<script lang="ts">
    import { assistantBuilderStore } from "$lib/plugins/assistants/stores/AssistantBuilderStore.svelte";
    import { ReleaseMode } from "$lib/plugins/assistants/types/assistant/ReleaseMode";
    import RadioSwitch from "$lib/plugins/assistants/components/radioSwitch/RadioSwitch.svelte";
    import RadioOption from "$lib/plugins/assistants/components/radioSwitch/RadioOption.svelte";
    import SquareLock02Icon from "$lib/components/ui/icons/iconset/SquareLock02Icon.svelte";
    import CheckmarkBadge01Icon from "$lib/components/ui/icons/iconset/CheckmarkBadge01Icon.svelte";
    import GlobeIcon from "$lib/components/ui/icons/iconset/GlobeIcon.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";


    const {__} = useTranslator()

    let currentValue = $derived(assistantBuilderStore.draft.releaseStage);

    function update(value: string) {
        assistantBuilderStore.set('releaseStage', value as ReleaseMode);
    }
</script>

<div class="input-container" class:renderBlock={true}>

    <p class="label">{__('assistants.builder.publish.release_stage.title')}</p>

    <RadioSwitch value={currentValue} onchange={update}>
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
    .label {
        font-size: var(--font-size-sm);
        font-weight: bold;
    }


</style>


