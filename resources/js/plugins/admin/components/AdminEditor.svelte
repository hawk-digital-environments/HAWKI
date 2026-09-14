<script lang="ts">
    import { tick, untrack } from 'svelte';
    import { ProviderDiscoverySchema, ModelInspectionSchema } from '../schemas/admin-actions.js';
    import { createForm, revalidateLogic } from '@tanstack/svelte-form';
    import type z from 'zod';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { createDraft, prepareValues, serverFieldErrors } from '../form.js';
    import { editorSchema, formValidationSchema } from '../forms/schemas.js';
    import { controlFor, isFieldVisible, normalizeControlValue } from '../forms/controls.js';
    import AdminValueInput from './AdminValueInput.svelte';
    import type { AdminField, AdminRow } from '../schemas/admin-content.js';
    import type { SectionId } from '../sections.js';

    let {
        section,
        fields,
        row,
        title,
        onSave,
        onClose,
        restoreFocus
    }: {
        section: SectionId;
        fields: AdminField[];
        row: AdminRow | null;
        title: string;
        onSave: (values: Record<string, unknown>) => Promise<void>;
        onClose: () => void;
        restoreFocus: () => HTMLElement | null;
    } = $props();
    const { __, hasLabel } = useTranslator();
    const app = useApp();
    const uid = $props.id();
    const initial = untrack(() => {
        const draft = createDraft(fields, row);
        for (const field of fields)
            draft[field.key] = normalizeControlValue(controlFor(section, field, draft, row), draft[field.key]);
        return draft;
    });
    const schema = untrack(() => editorSchema(section, fields, row));
    const validation = formValidationSchema(schema, (issue) => message(issue));
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
                await focusError();
                return;
            }
            try {
                await onSave(prepared.values);
                onClose();
            } catch (failure) {
                serverErrors = serverFieldErrors(failure);
                error = failure instanceof Error ? failure.message : __('admin.errors.save');
                await focusError();
            }
        }
    }));
    const formState = form.useSelector((state) => state);
    const visibleFields = $derived(fields.filter((field) => isFieldVisible(section, field, formState.current.values)));
    const busy = $derived(formState.current.isSubmitting || fieldBusy);
    // Discovery only suggests provider model ids that have not been added yet.
    type Suggestion = { value: string; label: string };
    const discovered = new Map<string, Suggestion[]>();
    let suggestions = $state<Suggestion[] | null>(null);
    let suggesting = $state(false);
    let discoveryFailed = $state(false);
    let inspecting = $state(false);
    let inspected = $state<'done' | 'failed' | null>(null);
    let inspection = 0;
    /** JSON snapshots of values this editor filled in itself; only those may be replaced by later metadata. */
    const adopted: Record<string, string> = {};
    const suggestProvider = $derived(
        section === 'models' && !row && app.can('providers.manage') ?
            (formState.current.values.provider_id ?? null)
        :   null
    );
    $effect(() => {
        const providerId = suggestProvider;
        suggestions = null;
        suggesting = false;
        discoveryFailed = false;
        if (providerId === null || providerId === '') {
            return;
        }
        const key = String(providerId);
        const cached = discovered.get(key);
        if (cached) {
            suggestions = cached;
            return;
        }
        let stale = false;
        suggesting = true;
        app.restApi
            .postToResourceAction(
                'admin-providers',
                `${encodeURIComponent(key)}/actions/discover`,
                {},
                { schema: ProviderDiscoverySchema }
            )
            .then((response) => {
                const models = response.models.map((model) => ({ value: model.model_id, label: model.label }));
                discovered.set(key, models);
                if (!stale) suggestions = models;
            })
            .catch((failure) => {
                console.warn('Model discovery failed.', failure);
                if (!stale) {
                    suggestions = [];
                    discoveryFailed = true;
                }
            })
            .finally(() => {
                if (!stale) suggesting = false;
            });
        return () => {
            stale = true;
        };
    });
    function modelIdHint(): string {
        if (suggestProvider === null || suggestProvider === '') return 'admin.model_id_provider_first';
        if (suggesting) return 'admin.model_id_loading';
        if (discoveryFailed) return 'admin.model_id_discovery_failed';
        if (inspecting) return 'admin.model_id_inspecting';
        if (inspected) return inspected === 'done' ? 'admin.model_id_inspected' : 'admin.model_id_inspect_failed';
        return suggestions?.length ? 'admin.model_id_suggestions' : 'admin.model_id_no_suggestions';
    }
    const inspectionStatus = $derived(
        inspecting ? __('admin.model_id_inspecting')
        : inspected === 'done' ? __('admin.model_id_inspected')
        : inspected === 'failed' ? __('admin.model_id_inspect_failed')
        : ''
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
    function adoptLabel(modelId: unknown) {
        const match = suggestions?.find((suggestion) => suggestion.value === modelId);
        if (match) adopt({ label: match.label });
    }
    /** A picked suggestion also pulls the provider's metadata for the remaining fields. */
    async function inspect(modelId: string) {
        adoptLabel(modelId);
        const providerId = suggestProvider;
        if (providerId === null || providerId === '') return;
        const token = ++inspection;
        inspecting = true;
        inspected = null;
        try {
            const response = await app.restApi.postToResourceAction(
                'admin-providers',
                `${encodeURIComponent(String(providerId))}/actions/inspect`,
                { model_id: modelId },
                { schema: ModelInspectionSchema }
            );
            if (token !== inspection) return;
            if (formState.current.values.model_id === modelId) adopt(response.model);
            inspected = 'done';
        } catch (failure) {
            console.warn('Model inspection failed.', failure);
            if (token === inspection) inspected = 'failed';
        } finally {
            if (token === inspection) inspecting = false;
        }
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
    function message(issue: z.core.$ZodIssue): string {
        const key = issue.path.map(String).at(-1);
        const labelKey = ['admin.form.labels.', 'admin.fields.'].map((prefix) => prefix + key).find(hasLabel);
        const label = labelKey ? __(labelKey) : key;
        let text = issue.message.startsWith('admin.') ? __(issue.message) : __('admin.validation.invalid');
        if (issue.code === 'too_small')
            text = __(issue.origin === 'string' ? 'admin.validation.min_length' : 'admin.validation.minimum', {
                value: String(issue.minimum)
            });
        if (issue.code === 'too_big')
            text = __(issue.origin === 'string' ? 'admin.validation.max_length' : 'admin.validation.maximum', {
                value: String(issue.maximum)
            });
        return label ? `${label}: ${text}` : text;
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
        if (!form.state.isValid) {
            error = __('admin.errors.form');
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
        {#if section === 'models' && !row}<p
                class="u-sr-only"
                role="status"
                aria-atomic="true"
            >
                {inspectionStatus}
            </p>{/if}
        {#if row?.mapped_roles && Array.isArray(row.mapped_roles) && row.mapped_roles.length}<p>
                {__('admin.mapped_roles_hint', { roles: row.mapped_roles.join(', ') })}
            </p>{/if}
        <div class="fields">
            {#each visibleFields as definition (definition.key)}
                <form.Field name={definition.key}>
                    {#snippet children(field)}
                        {@const control = controlFor(section, definition, formState.current.values, row)}
                        <AdminValueInput
                            id={`${uid}-${definition.key}`}
                            label={section === 'settings' ?
                                __('admin.settings_labels.' + row?.key)
                            :   __('admin.fields.' + definition.key)}
                            control={{
                                ...control,
                                label: definition.key,
                                suggestions:
                                    section === 'models' && !row && definition.key === 'model_id' ?
                                        (suggestions ?? undefined)
                                    :   undefined,
                                hint:
                                    control.hint ??
                                    (section === 'models' && !row && definition.key === 'model_id' ? modelIdHint()
                                    : section === 'users' && definition.key === 'password' ?
                                        row ? 'admin.local_password_replace'
                                        :   'admin.local_password_hint'
                                    : section === 'users' && definition.key === 'password_confirmation' ? undefined
                                    : definition.type.startsWith('secret') ?
                                        row?.[definition.key + '_set'] ?
                                            'admin.secret_replace'
                                        :   'admin.secret_hint'
                                    : definition.immutable && row ? 'admin.immutable_hint'
                                    : undefined)
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
                                if (section === 'models' && !row && definition.key === 'model_id') adoptLabel(value);
                            }}
                            onselect={section === 'models' && !row && definition.key === 'model_id' ?
                                (value) => void inspect(value)
                            :   undefined}
                            onBusyChange={(pending) => (fieldBusy = pending)}
                            onblur={field.handleBlur}
                            disabled={busy || control.disabled || (!!row && !!definition.immutable)}
                            error={fieldError(definition.key)}
                            secret={definition.type === 'secret-json'}
                        />
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
        grid-template-rows: auto minmax(0, 1fr) auto;
    }
    form {
        overflow-y: auto;
        min-height: 0;
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
