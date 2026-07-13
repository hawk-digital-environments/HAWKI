<script lang="ts">

    import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
    import {useApp} from "$lib/app/hooks/useApp.svelte";
    import {useStore} from "$lib/app/hooks/useStore.svelte";
    import Select, {type SelectOption} from "$plugins/assistants/components/select/Select.svelte";

    const {
        disabled = false,
        onchange,
    } = $props<{
        disabled?: boolean;
        onchange?: (value: string) => void;
    }>();

    const builder = useBuilderContext();

    const modelStore = useStore('ai-models');
    modelStore.loadData(useApp());

    const options = $derived<SelectOption[]>([
        {value: '', label: 'Select a Model', disabled: true},
        ...modelStore.models.map((model) => ({value: model.id, label: model.label})),
    ]);

</script>



<div class="input-container renderBlock">
    <label for="modelSelector">Empfohlenes Modell</label>

    <Select
        id="modelSelector"
        {options}
        value={builder.draft.model ?? ''}
        {disabled}
        onchange={(e) => onchange?.(e.currentTarget.value)}
    />
</div>
