<script lang="ts">
    import Input from '$lib/components/ui/input/Input.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import type { PermissionCatalogEntry } from '../../schemas/admin-content.js';
    import { permissionChoices, changePermission, permissionGroups } from '../../forms/authorization.js';
    let { id, label, catalog, value, onchange, onblur, disabled = false, error }: {
        id: string; label: string; catalog: PermissionCatalogEntry[]; value: unknown;
        onchange: (value: string[]) => void; onblur: () => void; disabled?: boolean; error?: string;
    } = $props();
    const { __ } = useTranslator();
    let search = $state('');
    const selected = $derived(Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : []);
    const choices = $derived(permissionChoices(catalog, selected));
    const visible = $derived(choices.filter((entry) =>
        // Existing ungrantable grants stay visible even while filtering.
        (!entry.grantable && selected.includes(entry.name)) ||
        `${entry.name} ${entry.title_label ? __(entry.title_label) : ''} ${entry.description_label ? __(entry.description_label) : ''}`.toLocaleLowerCase().includes(search.toLocaleLowerCase())
    ));
    // Derived from every choice, not the filtered ones, so the group order does not shift while typing.
    const groups = $derived(permissionGroups(choices));
</script>

<fieldset {id} {disabled} data-invalid={!!error} aria-describedby={`${id}-hint${error ? ` ${id}-error` : ''}`} onfocusout={onblur}>
    <legend>{label}</legend>
    <p id={`${id}-hint`}>{__('admin.permission_selector_hint')}</p>
    <Input type="search" aria-label={__('admin.permission_search')} bind:value={search} {disabled} />
    <p role="status" aria-atomic="true">{__('admin.permission_count', { count: String(visible.length) })}</p>
    {#each groups as group (group)}
        {@const entries = visible.filter((entry) => entry.group === group)}
        {#if entries.length}
            <fieldset class="group">
                <legend>{__('admin.permission_groups.' + group)}</legend>
                <div class="choices">
                    {#each entries as entry (entry.name)}
                        {@const key = `${id}-${entry.name}`}
                        <label class="choice">
                            <input type="checkbox" aria-invalid={!!error} checked={selected.includes(entry.name)} disabled={disabled || !entry.grantable}
                                aria-describedby={`${key}-description${!entry.grantable ? ` ${key}-locked` : ''}${error ? ` ${id}-error` : ''}`}
                                onchange={(event) => onchange(changePermission(catalog, selected, entry.name, event.currentTarget.checked))} />
                            <span>
                                <strong>{entry.title_label ? __(entry.title_label) : entry.name}</strong>
                                <span id={`${key}-description`}>{entry.description_label ? __(entry.description_label) : __('admin.permission_unknown')}</span>
                                <code>{entry.name}</code>
                                {#if !entry.grantable}<span id={`${key}-locked`}>{__(selected.includes(entry.name) ? 'admin.permission_preserved' : 'admin.permission_not_grantable')}</span>{/if}
                            </span>
                        </label>
                    {/each}
                </div>
            </fieldset>
        {/if}
    {/each}
    {#if error}<p class="error" id={`${id}-error`}>{error}</p>{/if}
</fieldset>

<style>
    fieldset { border: var(--border); border-radius: var(--corner-md); padding: var(--space-3); min-width: 0; }
    legend { font-weight: 600; padding-inline: var(--space-1); }
    p { font-size: var(--font-size-sm); margin-block: var(--space-2); }
    .group { margin-top: var(--space-4); }
    .choices { display: grid; gap: var(--space-3); }
    .choice { display: flex; gap: var(--space-2); align-items: start; }
    .choice input { margin-top: .3em; }
    .choice span span, code { display: block; font-size: var(--font-size-sm); }
    code { overflow-wrap: anywhere; }
    .error { color: var(--color-error); }
</style>
