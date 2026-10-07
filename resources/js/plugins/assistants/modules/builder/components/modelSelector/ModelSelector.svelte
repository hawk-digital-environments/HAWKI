<script lang="ts">

    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import {useApp} from "$lib/app/hooks/useApp.svelte";
    import {useStore} from "$lib/app/hooks/useStore.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import ModelPicker from "$plugins/core/modules/chat/components/composer/ModelPicker.svelte";
    import ModelPickerV2 from "$plugins/core/modules/chat/components/composer/ModelPickerV2.svelte";

    const {
        disabled = false,
        onchange,
    } = $props<{
        disabled?: boolean;
        onchange?: (value: string) => void;
    }>();

    const builder = useBuilderContext();
    const {__} = useTranslator();

    const modelStore = useStore('ai-models');
    modelStore.loadData(useApp());

    const experiments = useStore('experiments');

    // The assistant stores the provider-side model identifier (`model_id`,
    // e.g. "gpt-4.1-nano"); `getOneById` also resolves legacy drafts that
    // still store the model's numeric row id.
    const model = $derived(modelStore.getOneById(builder.draft.model ?? ''));

    function handleSelect(modelId: string): void {
        onchange?.(modelId);
    }

</script>



<!-- `data-model-picker-anchor`: the V2 popover spans this field instead of its small trigger. -->
<div class="input-container renderBlock" data-model-picker-anchor>
    <label for="modelSelector">{__('assistants.builder.model.input_model')}</label>

    <!-- Same picker (and experiment switch) as the chat composer. -->
    {#if experiments.isEnabled('modelPickerV2')}
        <ModelPickerV2 id="modelSelector" variant="field" {model} {disabled} onSelect={handleSelect}/>
    {:else}
        <ModelPicker id="modelSelector" variant="field" {model} {disabled} onSelect={handleSelect}/>
    {/if}
</div>
