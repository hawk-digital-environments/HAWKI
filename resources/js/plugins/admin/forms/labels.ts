import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';

/** Translates well-known property names; custom keys without a label show as typed. */
export function useFieldLabel() {
    const { __, hasLabel } = useTranslator();
    return (key: string) => (hasLabel('admin.form.labels.' + key) ? __('admin.form.labels.' + key) : key);
}
