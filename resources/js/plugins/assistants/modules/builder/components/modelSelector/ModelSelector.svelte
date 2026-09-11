<script lang="ts">

    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import {useApp} from "$lib/app/hooks/useApp.svelte";
    import {useStore} from "$lib/app/hooks/useStore.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import Select, {type SelectOption} from "$plugins/assistants/components/select/Select.svelte";

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

    const options = $derived<SelectOption[]>([
        {value: '', label: __('assistants.builder.model.select_model_placeholder'), disabled: true},
        // The assistant stores the provider-side model identifier (`model_id`,
        // e.g. "gpt-4.1-nano"), not the model's numeric row id.
        ...modelStore.models.map((model) => ({value: model.model_id, label: model.label})),
    ]);

</script>



<div class="input-container renderBlock">
    <label for="modelSelector">{__('assistants.builder.model.input_model')}</label>

    <Select
        id="modelSelector"
        {options}
        value={builder.draft.model ?? ''}
        {disabled}
        onchange={(e) => onchange?.(e.currentTarget.value)}
    />
</div>
