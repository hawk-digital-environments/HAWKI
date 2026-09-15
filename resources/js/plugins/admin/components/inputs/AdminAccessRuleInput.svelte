<script lang="ts">
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import type { AccessRule } from '../../schemas/admin-content.js';
    let { id, label, rules, value, onchange, onblur, disabled = false, error }: {
        id: string; label: string; rules: AccessRule[]; value: unknown; onchange: (value: string) => void;
        onblur: () => void; disabled?: boolean; error?: string;
    } = $props();
    const { __ } = useTranslator();
    const editable = $derived(!!rules.find((rule) => rule.name === value)?.grantable);
</script>

<!-- No explicit `role`: the implicit `group` role lets the `<legend>` name the radios. -->
<fieldset {id} disabled={disabled || !editable} data-invalid={!!error}
    aria-describedby={`${id}-hint${error ? ` ${id}-error` : ''}`} onfocusout={onblur}>
    <legend>{label}</legend>
    <p id={`${id}-hint`}>{__(editable ? 'admin.access_rule_hint' : 'admin.access_rule_locked')}</p>
    {#each rules as rule (rule.name)}
        <label>
            <!-- Neither `group` nor `radio` supports `aria-invalid`; the error text is linked below instead. -->
            <input type="radio" name={id} checked={rule.name === value}
                disabled={disabled || !editable || !rule.grantable}
                aria-describedby={`${id}-${rule.name}-description${error ? ` ${id}-error` : ''}`}
                onchange={() => { if (editable && rule.grantable) onchange(rule.name); }} />
            <span><strong>{__(rule.title_label)}</strong><span id={`${id}-${rule.name}-description`}>{__(rule.description_label)}{!rule.grantable ? ` ${__('admin.permission_not_grantable')}` : ''}</span></span>
        </label>
    {/each}
    {#if error}<p class="error" id={`${id}-error`}>{error}</p>{/if}
</fieldset>

<style>
    fieldset { border: var(--border); border-radius: var(--corner-md); padding: var(--space-3); min-width: 0; }
    legend { font-weight: 600; padding-inline: var(--space-1); }
    p, span span { font-size: var(--font-size-sm); }
    p { margin-block: var(--space-2); }
    label { display: flex; align-items: start; gap: var(--space-2); margin-block: var(--space-3); }
    input { margin-top: .3em; }
    span span { display: block; }
    .error { color: var(--color-error); }
</style>
