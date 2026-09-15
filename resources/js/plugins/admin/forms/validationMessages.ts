import type z from 'zod';
import type { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';

type Translator = Pick<ReturnType<typeof useTranslator>, '__' | 'hasLabel'>;

/** Human-readable text for a schema issue: "<field label>: <translated rule>". */
export function issueMessage(issue: z.core.$ZodIssue, { __, hasLabel }: Translator): string {
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
