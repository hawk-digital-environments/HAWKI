<script lang="ts">
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { newValueTypes, type NewValueType } from '../../forms/controls.js';

    let {
        value,
        onchange,
        disabled = false
    }: { value: NewValueType; onchange: (type: NewValueType) => void; disabled?: boolean } = $props();
    const { __ } = useTranslator();
</script>

<SingleSelect
    {value}
    onValueChange={(next: string) => onchange(next as NewValueType)}
    {disabled}
    triggerProps={{ 'aria-label': `${__('admin.form.value_type')}: ${__('admin.form.types.' + value)}` }}
    items={newValueTypes.map((type) => ({ value: type, label: __('admin.form.types.' + type) }))}
/>
