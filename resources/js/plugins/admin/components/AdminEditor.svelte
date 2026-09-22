<script lang="ts">
    import { tick, untrack } from 'svelte';
    import { createForm, revalidateLogic } from '@tanstack/svelte-form';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { createDraft, prepareValues, serverFieldErrors } from '../form.js';
    import { editorSchema, formValidationSchema } from '../forms/schemas.js';
    import { controlFor, isFieldVisible, normalizeControlValue, type Control } from '../forms/controls.js';
    import { fieldHint } from '../forms/hints.js';
    import { issueMessage } from '../forms/validationMessages.js';
    import { ModelLookup } from '../forms/modelLookup.svelte.js';
    import AdminPermissionInput from './inputs/AdminPermissionInput.svelte';
    import AdminAccessRuleInput from './inputs/AdminAccessRuleInput.svelte';
    import { roleLabel } from '../forms/authorization.js';
    import type { AdminContent } from '../schemas/admin-content.js';
    import AdminValueInput from './inputs/AdminValueInput.svelte';
    import type { AdminField, AdminRow } from '../schemas/admin-content.js';
    import type { SectionId } from '../sections.js';

    let {
        section,
        fields,
        content,
        row,
        title,
        onSave,
        onClose,
        restoreFocus
    }: {
        section: SectionId;
        fields: AdminField[];
        content: AdminContent | null;
        row: AdminRow | null;
        title: string;
        onSave: (values: Record<string, unknown>) => Promise<void>;
        onClose: () => void;
        restoreFocus: () => HTMLElement | null;
    } = $props();
    const translator = useTranslator();
    const { __ } = translator;
    const app = useApp();
    const uid = $props.id();
    const initial = untrack(() => {
        const draft = createDraft(fields, row);
        for (const field of fields)
            draft[field.key] = normalizeControlValue(controlFor(section, field, draft, row), draft[field.key]);
        return draft;
    });
    const schema = untrack(() => editorSchema(section, fields, row));
    const validation = formValidationSchema(schema, (issue) => issueMessage(issue, translator));
    let error = $state('');
    let fieldBusy = $state(false);
    let serverErrors = $state<Record<string, string>>({});
    let discard = $state(false);
    /** Element focused inside the editor when the discard prompt opened; focus returns there on cancel. */
    let discardOrigin: HTMLElement | null = null;
    let formElement = $state<HTMLFormElement>();
    const form = createForm(() => ({
        defaultValues: initial,
        validationLogic: revalidateLogic({ mode: 'blur', modeAfterSubmission: 'change' }),
        validators: { onDynamic: validation, onSubmit: validation },
        onSubmit: async ({ value }) => {
            if (fieldBusy) return;
            error = '';
            serverErrors = {};
            const prepared = prepareValues(fields, schema.parse(value));
            if (Object.keys(prepared.errors).length) {
                serverErrors = Object.fromEntries(
                    Object.entries(prepared.errors).map(([key, message]) => [key, __(message)])
                );
                error = __('admin.errors.form');
                return;
            }
            try {
                await onSave(prepared.values);
                onClose();
            } catch (failure) {
                serverErrors = serverFieldErrors(failure);
                error = failure instanceof Error ? failure.message : __('admin.errors.save');
            }
        }
    }));
    const formState = form.useSelector((state) => state);
    const visibleFields = $derived(fields.filter((field) => isFieldVisible(section, field, formState.current.values)));
    const busy = $derived(formState.current.isSubmitting || fieldBusy || app.authorizationRefreshing);
    /** Changing a tool's access rule rewrites role grants, so it needs both permissions. */
    const accessRuleLocked = $derived(!app.can('mcp.manage') || !app.can('roles.manage'));
    /** JSON snapshots of values this editor filled in itself; only those may be replaced by later metadata. */
    const adopted: Record<string, string> = {};
    /** New models get provider model id suggestions plus metadata for the picked one. */
    const lookup = untrack(() =>
        section === 'models' && !row && app.can('providers.manage') ?
            new ModelLookup({
                restApi: app.restApi,
                providerId: () => formState.current.values.provider_id,
                modelId: () => formState.current.values.model_id,
                adopt
            })
        :   null
    );
    /** Fill fields from provider metadata, but never overwrite what the admin typed themselves. */
    function adopt(values: Record<string, unknown>) {
        const draft = createDraft(fields, { id: '', ...values });
        for (const field of fields) {
            if (!Object.hasOwn(values, field.key) || ['model_id', 'provider_id'].includes(field.key)) continue;
            const current = JSON.stringify(formState.current.values[field.key] ?? null);
            if (current !== JSON.stringify(initial[field.key] ?? null) && current !== adopted[field.key]) continue;
            const next = normalizeControlValue(controlFor(section, field, draft, row), draft[field.key]);
            adopted[field.key] = JSON.stringify(next ?? null);
            form.setFieldValue(field.key, next);
        }
    }
    function hintFor(definition: AdminField, control: Control): string | undefined {
        if (control.hint) return control.hint;
        if (lookup && definition.key === 'model_id') return lookup.hint;
        return fieldHint(section, definition, row);
    }
    function labelFor(definition: AdminField): string {
        return section === 'settings' ?
                __('admin.settings_labels.' + row?.key)
            :   __('admin.fields.' + definition.key);
    }
    function close() {
        if (busy) return;
        if (!formState.current.isDirty) {
            onClose();
            return;
        }
        // An outside click moves focus out of the dialog; fall back to the
        // dialog surface so cancelling the prompt lands inside the editor.
        const dialog = formElement?.closest<HTMLElement>('[role="dialog"]') ?? null;
        const active = document.activeElement;
        discardOrigin = active instanceof HTMLElement && dialog?.contains(active) ? active : dialog;
        discard = true;
    }
    function fieldError(key: string): string | undefined {
        if (serverErrors[key]) return serverErrors[key];
        if (!formState.current.submissionAttempts && !formState.current.fieldMeta[key]?.isTouched) return;
        const issues = formState.current.errors.flatMap((errors) =>
            Object.entries(errors)
                .filter(([path]) => path === key || path.startsWith(key + '.') || path.startsWith(key + '['))
                .flatMap(([, issues]) => issues)
        );
        return [...new Set(issues.map((issue) => issue.message))].join('\n') || undefined;
    }
    /** Move focus into the first field (in form order) that TanStack or the server flagged as invalid. */
    async function focusError() {
        await tick();
        const key = visibleFields.find((field) => fieldError(field.key))?.key;
        const root = key ? formElement?.querySelector<HTMLElement>(`#${CSS.escape(`${uid}-${key}`)}`) : null;
        const target =
            root?.matches('fieldset') ?
                ['[aria-invalid="true"]', 'textarea, input:not([type="hidden"]), [role="combobox"]', 'button']
                    .map((selector) => root.querySelector<HTMLElement>(selector))
                    .find(Boolean)
            :   root;
        target?.scrollIntoView({ block: 'center' });
        target?.focus({ preventScroll: true });
    }
    async function submit(event: SubmitEvent) {
        event.preventDefault();
        event.stopPropagation();
        if (busy) return;
        error = '';
        serverErrors = {};
        await form.handleSubmit();
        if (error || !form.state.isValid) {
            if (!error) error = __('admin.errors.form');
            await focusError();
        }
    }
</script>

<Dialog
    open={true}
    {title}
    description={__('admin.editor_hint')}
    onOpenChange={(open) => {
        if (!open) close();
    }}
    contentProps={{
        class: 'admin-editor-dialog',
        onCloseAutoFocus: (event) => {
            event.preventDefault();
            restoreFocus()?.focus();
        }
    }}
>
    <form
        id={`${uid}-form`}
        bind:this={formElement}
        onsubmit={submit}
        aria-busy={busy}
        novalidate
    >
        {#if error}<p role="alert">{error}</p>{/if}
        {#if lookup}<p
                class="u-sr-only"
                role="status"
                aria-atomic="true"
            >
                {lookup.status ? __(lookup.status) : ''}
            </p>{/if}
        {#if row?.mapped_roles && Array.isArray(row.mapped_roles) && row.mapped_roles.length}<p>
                {__('admin.mapped_roles_hint', { roles: row.mapped_roles.map((id) => roleLabel(Number(id), content?.role_catalog ?? [], fields, __)).join(', ') })}
            </p>{/if}
        <div class="fields">
            {#each visibleFields as definition (definition.key)}
                <form.Field name={definition.key}>
                    {#snippet children(field)}
                        {@const control = controlFor(section, definition, formState.current.values, row)}
                        {@const modelLookup = definition.key === 'model_id' ? lookup : null}
                        {#if control.type === 'permissions'}
                            <AdminPermissionInput
                                id={`${uid}-${definition.key}`}
                                label={labelFor(definition)}
                                catalog={content?.permission_catalog ?? []}
                                value={field.state.value}
                                onchange={(value) => {
                                    delete serverErrors[definition.key];
                                    field.handleChange(value);
                                }}
                                onblur={field.handleBlur}
                                disabled={busy || control.disabled}
                                error={fieldError(definition.key)}
                            />
                        {:else if control.type === 'access-rule'}
                            <AdminAccessRuleInput
                                id={`${uid}-${definition.key}`}
                                label={labelFor(definition)}
                                rules={content?.access_rules ?? []}
                                value={field.state.value}
                                onchange={(value) => {
                                    delete serverErrors[definition.key];
                                    field.handleChange(value);
                                }}
                                onblur={field.handleBlur}
                                disabled={busy || control.disabled || accessRuleLocked}
                                error={fieldError(definition.key)}
                            />
                        {:else}
                        <AdminValueInput
                            id={`${uid}-${definition.key}`}
                            label={labelFor(definition)}
                            control={{
                                ...control,
                                label: definition.key,
                                options: ['roles', 'role_id'].includes(definition.key) ? control.options?.map((option) => ({
                                    ...option,
                                    label: roleLabel(Number(option.value), content?.role_catalog ?? [], fields, __)
                                })) : control.options,
                                suggestions: modelLookup?.suggestions ?? undefined,
                                hint: hintFor(definition, control)
                            }}
                            value={field.state.value}
                            onchange={(value) => {
                                delete serverErrors[definition.key];
                                field.handleChange(value);
                                if (
                                    section === 'system-models' &&
                                    definition.key === 'model_type' &&
                                    value === 'translation'
                                )
                                    form.setFieldValue('prompts', {});
                                modelLookup?.adoptLabel(value);
                            }}
                            onselect={modelLookup ? (value) => void modelLookup.inspect(value) : undefined}
                            onBusyChange={(pending) => (fieldBusy = pending)}
                            onblur={field.handleBlur}
                            disabled={busy || control.disabled || (!!row && !!definition.immutable)}
                            error={fieldError(definition.key)}
                            secret={definition.type === 'secret-json'}
                        />
                        {/if}
                    {/snippet}
                </form.Field>
            {/each}
        </div>
    </form>
    {#snippet footer()}
        <Button
            variant="ghost"
            onclick={close}
            disabled={busy}>{__('admin.cancel')}</Button
        >
        <Button
            type="submit"
            form={`${uid}-form`}
            variant="fill"
            disabled={busy}>{__(busy ? 'admin.saving' : 'admin.save')}</Button
        >
    {/snippet}
</Dialog>
<ConfirmDialog
    open={discard}
    onOpenChange={(open) => (discard = open)}
    title={__('admin.discard_title')}
    description={__('admin.discard_description')}
    okLabel={__('admin.discard')}
    onConfirm={onClose}
    restoreFocusTo={() => (discardOrigin?.isConnected ? discardOrigin : restoreFocus())}
/>

<style>
    :global(.dialog-content.admin-editor-dialog) {
        box-sizing: border-box;
        width: min(56rem, calc(100vw - 2rem));
        max-width: 56rem;
        max-height: calc(100dvh - 2rem);
    }
    form {
        padding: var(--space-1);
    }
    .fields {
        display: grid;
        gap: var(--space-5);
    }
    [role='alert'] {
        padding: var(--space-3);
        border: var(--border);
        border-color: var(--color-error);
        color: var(--color-error);
        margin-bottom: var(--space-3);
    }
</style>
