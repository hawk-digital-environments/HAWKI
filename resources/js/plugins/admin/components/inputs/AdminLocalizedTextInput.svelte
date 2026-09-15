<script lang="ts">
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import SingleSelect from '$lib/components/ui/select/SingleSelect.svelte';
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { record, type Control } from '../../forms/controls.js';
    import { translateLocales } from '../../forms/translateLocales.js';

    let {
        id,
        label,
        control,
        value,
        onchange,
        disabled = false,
        error,
        describedby
    }: {
        id: string;
        label: string;
        control: Control;
        value: unknown;
        onchange: (value: unknown) => void;
        disabled?: boolean;
        error?: string;
        /** Ids of the hint and error paragraphs the enclosing fieldset renders. */
        describedby?: string;
    } = $props();
    const { __ } = useTranslator();
    const app = useApp();
    let locale = $state('en_US');
    let preview = $state(false);
    let translating = $state(false);
    let translateStatus = $state('');
    let translateError = $state('');
    const locales = [
        { value: 'en_US', label: () => __('admin.english') },
        { value: 'de_DE', label: () => __('admin.german') }
    ];
    const markdown = $derived(control.type === 'markdown-locales');
    const object = $derived(record(value));
    const text = $derived(String(object[locale] ?? ''));
    const localeLabel = $derived(locales.find((entry) => entry.value === locale)?.label() ?? locale);
    function update(next: string) {
        onchange({ ...object, [locale]: next });
    }
    async function translateFromCurrentLocale() {
        const source = text.trim();
        if (!source || translating) return;
        const models = app.stores.get('ai-models');
        const model =
            models.getSystemModelByType('translation') ?? models.getSystemModelByType('default') ?? models.models[0];
        translateStatus = '';
        translateError = '';
        if (!model) {
            translateError = __('admin.errors.translate_model');
            return;
        }
        const targets = locales.map((entry) => ({
            lang: entry.value,
            name:
                app.config.get().locale.available.find((available) => available.lang === entry.value)
                    ?.nameInLanguage ?? entry.label()
        }));
        translating = true;
        try {
            const translations = await translateLocales({
                text: source,
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
</script>

<div class="controls">
    <SingleSelect
        value={locale}
        onValueChange={(next: string) => (locale = next)}
        {disabled}
        triggerProps={{ 'aria-label': `${__('admin.fields.locale')}: ${localeLabel}` }}
        items={locales.map((entry) => ({ value: entry.value, label: entry.label() }))}
    />
    {#if markdown}
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
        disabled={disabled || translating || !text.trim()}
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
{#if markdown && preview}<div class="preview">
        <Markdown
            message={text}
            headingBaseLevel={3}
        />
    </div>
{:else}<Textarea
        rows={control.rows ?? (markdown ? 12 : 6)}
        aria-label={`${label}: ${localeLabel}`}
        lang={locale === 'de_DE' ? 'de' : 'en'}
        value={text}
        oninput={(event) => update(event.currentTarget.value)}
        {disabled}
        aria-invalid={!!error}
        aria-describedby={describedby}
    />{/if}

<style>
    .controls {
        display: flex;
        flex-wrap: wrap;
        align-items: end;
        gap: var(--space-2);
        margin-block: var(--space-3);
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
</style>
