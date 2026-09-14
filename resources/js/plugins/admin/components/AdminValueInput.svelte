<script lang="ts">
    import { tick } from 'svelte';
    import Input from '$lib/components/ui/input/Input.svelte';
    import Combobox from '$lib/components/ui/combobox/Combobox.svelte';
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import Field from '$lib/components/ui/form/Field.svelte';
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { record, inferControl, type Control } from '../forms/controls.js';
    import { translateLocales } from '../forms/translateLocales.js';
    import AdminValueInput from './AdminValueInput.svelte';
    import AdminProviderIconInput from './AdminProviderIconInput.svelte';
    import AdminPricingInput from './AdminPricingInput.svelte';

    let {
        id,
        label,
        control,
        value,
        onchange,
        onselect,
        onBusyChange = () => {},
        onblur = () => {},
        disabled = false,
        error,
        secret = false,
        nested = false
    }: {
        id: string;
        label: string;
        control: Control;
        value: unknown;
        onchange: (value: unknown) => void;
        /** Fires when the user picks one of `control.suggestions`, after `onchange` carried its value. */
        onselect?: (value: string) => void;
        onblur?: () => void;
        onBusyChange?: (busy: boolean) => void;
        disabled?: boolean;
        error?: string;
        secret?: boolean;
        nested?: boolean;
    } = $props();
    const { __, hasLabel } = useTranslator();
    const app = useApp();
    let newKey = $state('');
    let newType = $state('text');
    let search = $state('');
    let addError = $state('');
    let showAdd = $state(false);
    let locale = $state('en_US');
    let preview = $state(false);
    let translating = $state(false);
    let translateStatus = $state('');
    let translateError = $state('');
    const locales = [
        { value: 'en_US', label: () => __('admin.english') },
        { value: 'de_DE', label: () => __('admin.german') }
    ];
    const localeLabel = $derived(locales.find((entry) => entry.value === locale)?.label() ?? locale);
    const items = $derived(Array.isArray(value) ? value : []);
    const object = $derived(record(value));
    const selectedOption = $derived(control.options?.find((option) => option.value === value));
    const entries = $derived(Object.entries(object).filter(([key]) => !Object.hasOwn(control.fields ?? {}, key)));
    const options = $derived([
        ...(control.options ?? []),
        ...items
            .filter((item) => !control.options?.some((option) => option.value === item))
            .map((item) => ({ value: typeof item === 'number' ? item : String(item), label: String(item) }))
    ]);
    const describedby = $derived(
        error ? `${id}-error`
        : control.hint ? `${id}-hint`
        : undefined
    );
    function fieldLabel(key: string) {
        return hasLabel('admin.form.labels.' + key) ? __('admin.form.labels.' + key) : key;
    }
    function update(key: string, next: unknown) {
        const changed = { ...object };
        if (next === undefined) delete changed[key];
        else changed[key] = next;
        onchange(changed);
    }
    function newValue(type = control.item?.type ?? newType) {
        return (
            type === 'number' ? 0
            : type === 'boolean' ? false
            : type === 'object' ? {}
            : type === 'list' ? []
            : ''
        );
    }
    async function addEntry() {
        const key = newKey.trim();
        if (
            !key ||
            ['__proto__', 'constructor', 'prototype'].includes(key) ||
            Object.hasOwn(object, key) ||
            Object.hasOwn(control.fields ?? {}, key)
        ) {
            addError = __('admin.form.unique_key');
            return;
        }
        update(key, newValue());
        newKey = '';
        addError = '';
        showAdd = false;
        await tick();
        (
            document.getElementById(`${id}-custom-${key}`)?.querySelector<HTMLElement>('input,button,textarea') ??
            document.getElementById(`${id}-custom-${key}`) ??
            document.getElementById(`${id}-add-toggle`)
        )?.focus();
    }
    async function revealAdd() {
        showAdd = true;
        await tick();
        document.getElementById(`${id}-key`)?.focus();
    }
    async function translateFromCurrentLocale() {
        const text = String(object[locale] ?? '').trim();
        if (!text || translating) return;
        const models = app.stores.get('ai-models');
        const model = models.getSystemModelByType('translation') ?? models.getSystemModelByType('default') ?? models.models[0];
        translateStatus = '';
        translateError = '';
        if (!model) {
            translateError = __('admin.errors.translate_model');
            return;
        }
        const targets = locales.map((entry) => ({
            lang: entry.value,
            name: app.config.get().locale.available.find((available) => available.lang === entry.value)?.nameInLanguage ?? entry.label()
        }));
        translating = true;
        try {
            const translations = await translateLocales({
                text,
                source: targets.find((target) => target.lang === locale)!,
                targets,
                complete: (messages) => app.aiApi.text({ model: model.model_id, messages })
            });
            onchange({ ...object, ...translations });
            translateStatus = __('admin.translated', {
                locales: Object.keys(translations)
                    .map((lang) => locales.find((entry) => entry.value === lang)?.label() ?? lang)
                    .join(', ')
            });
        } catch (failure) {
            console.warn('Translation failed.', failure);
            translateError = __('admin.errors.translate_failed');
        } finally {
            translating = false;
        }
    }
    function removeItem(index: number, event: MouseEvent) {
        const row = (event.currentTarget as HTMLElement).closest('li');
        const target =
            row?.nextElementSibling?.querySelector<HTMLElement>('input,button') ??
            row?.previousElementSibling?.querySelector<HTMLElement>('input,button') ??
            document.getElementById(`${id}-add`);
        target?.focus();
        onchange(items.filter((_, i) => i !== index));
    }
</script>

{#if control.type === 'provider-icon'}
    <AdminProviderIconInput {onBusyChange} {id} {label} {value} {onchange} {onblur} {disabled} {error} />
{:else if ['object', 'multi', 'list', 'localized-text', 'markdown-locales', 'pricing'].includes(control.type)}
    <fieldset
        {id}
        {disabled}
        class:nested
        aria-describedby={describedby}
        data-invalid={!!error}
        onfocusout={onblur}
    >
        <legend>{label}</legend>
        {#if control.hint}<p
                id={`${id}-hint`}
                class="hint"
            >
                {__(control.hint)}
            </p>{/if}
        {#if control.type === 'object'}
            <div class="fields">
                {#each Object.entries(control.fields ?? {}) as [key, child] (key)}
                    <AdminValueInput
                        id={`${id}-${key}`}
                        label={fieldLabel(key)}
                        control={child}
                        value={object[key]}
                        onchange={(next) => update(key, next)}
                        {disabled}
                        {secret}
                        nested
                    />
                {/each}
                {#each entries as [key, child] (key)}
                    <div class="entry">
                        <AdminValueInput
                            id={`${id}-custom-${key}`}
                            label={key}
                            control={control.item ?? inferControl(child)}
                            value={child}
                            onchange={(next) => update(key, next)}
                            {disabled}
                            {secret}
                            nested
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            aria-label={__('admin.form.remove_named', { name: key })}
                            onclick={() => {
                                (
                                    document.getElementById(`${id}-key`) ??
                                    document.getElementById(`${id}-add-toggle`)
                                )?.focus();
                                update(key, undefined);
                            }}>{__('admin.form.remove')}</Button
                        >
                    </div>
                {/each}
            </div>
            {#if control.custom && !showAdd}
                <Button
                    id={`${id}-add-toggle`}
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="add-toggle"
                    {disabled}
                    onclick={revealAdd}>{__('admin.form.add_property')}</Button
                >
            {:else if control.custom}
                <div class="add-entry">
                    <Input
                        id={`${id}-key`}
                        aria-label={__('admin.form.property_name', { name: label })}
                        bind:value={newKey}
                        {disabled}
                    />
                    {#if !control.item}<SingleSelect
                            value={newType}
                            onValueChange={(next: string) => (newType = next)}
                            {disabled}
                            triggerProps={{
                                'aria-label': `${__('admin.form.value_type')}: ${__('admin.form.types.' + newType)}`
                            }}
                            items={['text', 'number', 'boolean', 'object', 'list'].map((type) => ({
                                value: type,
                                label: __('admin.form.types.' + type)
                            }))}
                        />{/if}
                    <Button
                        type="button"
                        size="sm"
                        variant="stroke"
                        {disabled}
                        onclick={addEntry}>{__('admin.form.add_property')}</Button
                    >
                </div>
                {#if addError}<p role="alert">{addError}</p>{/if}
            {/if}
        {:else if control.type === 'multi'}
            {#if options.length > 10}<Input
                    aria-label={__('admin.form.filter', { name: label })}
                    type="search"
                    bind:value={search}
                    {disabled}
                />{/if}
            <div class="choices">
                {#each options.filter((option) => !search || option.label
                            .toLowerCase()
                            .includes(search.toLowerCase())) as option (option.value)}
                    <label
                        class="choice"
                        data-capability={control.label === 'native_capabilities' ? String(option.value) : undefined}
                        ><input
                            type="checkbox"
                            {disabled}
                            checked={items.includes(option.value)}
                            onchange={(event) =>
                                onchange(
                                    event.currentTarget.checked ?
                                        [...items, option.value]
                                    :   items.filter((item) => item !== option.value)
                                )}
                        />
                        <span
                            >{control.label === 'permissions' ?
                                __('admin.permissions.' + String(option.value).replaceAll('.', '_'))
                            :   fieldLabel(option.label)}</span
                        >
                    </label>
                {/each}
            </div>
            {#if control.custom}
                <div class="add-entry">
                    <Input
                        id={`${id}-add`}
                        aria-label={__('admin.form.new_item', { name: label })}
                        bind:value={newKey}
                        {disabled}
                    /><Button
                        type="button"
                        size="sm"
                        variant="stroke"
                        disabled={disabled || !newKey.trim()}
                        onclick={() => {
                            if (!items.includes(newKey.trim())) onchange([...items, newKey.trim()]);
                            newKey = '';
                        }}>{__('admin.form.add')}</Button
                    >
                </div>
            {/if}
        {:else if control.type === 'list'}
            <ol class="list">
                {#each items as item, index}
                    <li class="entry">
                        <AdminValueInput
                            id={`${id}-${index}`}
                            label={__('admin.form.item', { number: String(index + 1) })}
                            control={control.item ?? inferControl(item)}
                            value={item}
                            onchange={(next) => onchange(items.map((current, i) => (i === index ? next : current)))}
                            {disabled}
                            {secret}
                            nested
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            {disabled}
                            aria-label={__('admin.form.remove_item', { number: String(index + 1) })}
                            onclick={(event) => removeItem(index, event)}>{__('admin.form.remove')}</Button
                        >
                    </li>
                {/each}
            </ol>
            <div class="add-entry">
                {#if !control.item}<SingleSelect
                        value={newType}
                        onValueChange={(next: string) => (newType = next)}
                        {disabled}
                        triggerProps={{
                            'aria-label': `${__('admin.form.value_type')}: ${__('admin.form.types.' + newType)}`
                        }}
                        items={['text', 'number', 'boolean', 'object', 'list'].map((type) => ({
                            value: type,
                            label: __('admin.form.types.' + type)
                        }))}
                    />{/if}
                <Button
                    id={`${id}-add`}
                    type="button"
                    size="sm"
                    variant="stroke"
                    {disabled}
                    onclick={() => onchange([...items, newValue()])}>{__('admin.form.add')}</Button
                >
            </div>
        {:else if ['localized-text', 'markdown-locales'].includes(control.type)}
            <div class="preview-controls">
                <SingleSelect
                    value={locale}
                    onValueChange={(next: string) => (locale = next)}
                    {disabled}
                    triggerProps={{
                        'aria-label': `${__('admin.fields.locale')}: ${__(locale === 'en_US' ? 'admin.english' : 'admin.german')}`
                    }}
                    items={locales.map((entry) => ({ value: entry.value, label: entry.label() }))}
                />
                {#if control.type === 'markdown-locales'}
                    <Button
                        variant="stroke"
                        size="sm"
                        type="button"
                        {disabled}
                        onclick={() => (preview = !preview)}>{__(preview ? 'admin.edit' : 'admin.preview')}</Button
                    >
                {/if}
                <Button
                    variant="stroke"
                    size="sm"
                    type="button"
                    disabled={disabled || translating || !String(object[locale] ?? '').trim()}
                    aria-describedby={`${id}-translate-hint`}
                    onclick={translateFromCurrentLocale}>{__(translating ? 'admin.translating' : 'admin.translate')}</Button
                >
            </div>
            <p
                id={`${id}-translate-hint`}
                class="hint"
            >
                {__('admin.translate_hint')}
            </p>
            <p
                class="status"
                role="status"
                aria-atomic="true"
            >
                {translateStatus}
            </p>
            {#if translateError}<p
                    class="error"
                    role="alert"
                >
                    {translateError}
                </p>{/if}
            {#if control.type === 'markdown-locales' && preview}<div class="preview">
                    <Markdown
                        message={String(object[locale] ?? '')}
                        headingBaseLevel={3}
                    />
                </div>
            {:else}<Textarea
                    rows={control.rows ?? (control.type === 'localized-text' ? 6 : 12)}
                    aria-label={`${label}: ${localeLabel}`}
                    lang={locale === 'de_DE' ? 'de' : 'en'}
                    value={String(object[locale] ?? '')}
                    oninput={(event) => update(locale, event.currentTarget.value)}
                    {disabled}
                    aria-invalid={!!error}
                    aria-describedby={describedby}
                />{/if}
        {:else if control.type === 'pricing'}
            <AdminPricingInput
                {id}
                {value}
                {onchange}
                {disabled}
            />
        {/if}
        {#if error}<p
                id={`${id}-error`}
                class="error"
            >
                {error}
            </p>{/if}
    </fieldset>
{:else}
    <Field
        {id}
        {label}
        {error}
        hint={control.hint ? __(control.hint) : undefined}
    >
        {#snippet children(props)}
            {#if control.type === 'boolean' && control.optional}
                <SingleSelect
                    triggerProps={{
                        ...props,
                        'aria-label': `${label}: ${__(
                            value === true ? 'admin.yes'
                            : value === false ? 'admin.no'
                            : 'admin.form.inherit'
                        )}`,
                        onblur
                    }}
                    {disabled}
                    value={value === true ? 'true'
                    : value === false ? 'false'
                    : 'default'}
                    onValueChange={(next: string) => onchange(next === 'default' ? undefined : next === 'true')}
                    items={[
                        { value: 'default', label: __('admin.form.inherit') },
                        { value: 'true', label: __('admin.yes') },
                        { value: 'false', label: __('admin.no') }
                    ]}
                />
            {:else if control.type === 'boolean'}
                <Switch
                    {...props}
                    aria-label={label}
                    bind:checked={() => value === true, (next) => onchange(next)}
                    {disabled}
                    {onblur}
                />
            {:else if control.type === 'select'}
                <SingleSelect
                    triggerProps={{
                        ...props,
                        'aria-label': `${label}: ${selectedOption ? fieldLabel(selectedOption.label) : __('admin.choose')}`,
                        onblur
                    }}
                    {disabled}
                    placeholder={__('admin.choose')}
                    items={(control.options ?? []).map((option) => ({
                        value: String(option.value),
                        label: fieldLabel(option.label)
                    }))}
                    value={value == null ? '' : String(value)}
                    onValueChange={(next: string) =>
                        onchange(control.options?.find((option) => String(option.value) === next)?.value ?? '')}
                />
            {:else if control.type === 'textarea'}
                <Textarea
                    {...props}
                    rows={6}
                    value={String(value ?? '')}
                    oninput={(event) => onchange(event.currentTarget.value)}
                    {onblur}
                    {disabled}
                />
            {:else if control.suggestions}
                <Combobox
                    value={value == null ? '' : String(value)}
                    items={control.suggestions}
                    onValueChange={(next) => onchange(control.optional && next === '' ? undefined : next)}
                    onSelect={(item) => onselect?.(item.value)}
                    {disabled}
                    inputProps={{ ...props, onblur }}
                    emptyText={__('admin.form.suggestions_empty')}
                    moreText={(hidden) => __('admin.form.suggestions_more', { count: String(hidden) })}
                    toggleLabel={__('admin.form.suggestions_toggle', { name: label })}
                />
            {:else}
                <!-- The form owns the value: a no-op setter keeps programmatic updates flowing after the user edited the field. -->
                <Input
                    {...props}
                    type={control.type === 'secret' || (secret && control.type === 'text') ? 'password'
                    : control.type === 'datetime' ? 'datetime-local'
                    : control.type === 'number' ? 'number'
                    : control.type === 'url' ? 'url'
                    : 'text'}
                    min={control.min}
                    max={control.max}
                    step={control.step}
                    bind:value={() => (value == null ? '' : String(value)), () => {}}
                    oninput={(event) =>
                        onchange(
                            control.type === 'number' ?
                                event.currentTarget.value === '' ?
                                    undefined
                                :   event.currentTarget.valueAsNumber
                            : control.optional && event.currentTarget.value === '' ? undefined
                            : event.currentTarget.value
                        )}
                    {onblur}
                    {disabled}
                    autocomplete={secret || control.type === 'secret' ? 'new-password' : 'off'}
                />
            {/if}
        {/snippet}
    </Field>
{/if}

<style>
    fieldset {
        border: var(--border);
        padding: var(--space-3);
        border-radius: var(--corner-md);
        min-width: 0;
    }
    legend {
        font-weight: 600;
        font-size: var(--font-size-sm);
        padding-inline: var(--space-1);
    }
    fieldset.nested {
        border: 0;
        border-inline-start: var(--border-strong);
        border-radius: 0;
        padding-block: 0;
        padding-inline: var(--space-3) 0;
    }
    fieldset[data-invalid='true'],
    fieldset.nested[data-invalid='true'] {
        border-color: var(--color-error);
    }
    fieldset[data-invalid='true'] > legend {
        color: var(--color-error);
    }
    fieldset.nested > legend {
        padding-inline: 0;
        margin-block-end: var(--space-2);
        color: var(--color-text-muted);
    }
    .fields {
        display: grid;
        gap: var(--space-4);
    }
    .choices {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--space-2);
        max-height: 18rem;
        overflow-y: auto;
        margin-block: var(--space-2);
    }
    .choice {
        display: flex;
        align-items: start;
        gap: var(--space-2);
        font-size: var(--font-size-sm);
        padding: var(--space-1);
        overflow-wrap: anywhere;
    }
    .choice input {
        margin-top: 0.2em;
    }
    .choice[data-capability] {
        color: var(--capability-color);
        border-radius: var(--corner-xs);
        background-color: var(--capability-surface);
    }
    .choice[data-capability] input {
        accent-color: var(--capability-color);
    }
    .entry {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: var(--space-2);
        align-items: end;
    }
    .add-entry,
    .preview-controls {
        display: flex;
        flex-wrap: wrap;
        align-items: end;
        gap: var(--space-2);
        margin-block: var(--space-3);
    }
    :global(.add-toggle) {
        justify-self: start;
        margin-block-start: var(--space-2);
    }
    .add-entry :global(input) {
        flex: 1;
        min-width: 8rem;
    }
    .list {
        padding: 0;
        list-style: none;
        display: grid;
        gap: var(--space-3);
    }
    .hint,
    .error,
    .status {
        font-size: var(--font-size-sm);
        margin-block: var(--space-2);
    }
    .hint {
        color: var(--color-text-muted);
    }
    .status {
        margin: 0;
    }
    .error {
        white-space: pre-line;
        color: var(--color-error);
        font-weight: 600;
    }
    @media (--bp-sm-and-smaller) {
        .choices {
            grid-template-columns: 1fr;
        }
    }
</style>
