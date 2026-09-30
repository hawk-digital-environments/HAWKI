<!--
  @component A read-only rendering of one builder field (system prompt,
  description, name, ...) that lets an admin flag it for the creator, with a
  comment. Long-text fields (`selectable`, the default) additionally let the
  admin select a passage and flag just that excerpt; short/whole-value fields
  only support flagging the field as a whole (no selection makes sense on,
  say, a boolean or a handle). No text-selection/highlighting primitive
  exists elsewhere in the app, so the selection half is purpose-built: plain
  browser `window.getSelection()` over rendered text (not a textarea, which
  wouldn't expose a selection this way), excerpts stored as plain substrings
  and re-highlighted by best-effort string matching — not character-offset
  anchoring, so it stays correct even if the field text changes later.
-->
<script lang="ts">
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import Flag01Icon from '$lib/components/ui/icons/iconset/Flag01Icon.svelte';
    import type { AssistantFieldFlag } from '$plugins/assistants/api/schemas/resources/assistant-field-flag.schema';
    import {
        createAssistantFieldFlag,
        deleteAssistantFieldFlag,
        setAssistantFieldFlagResolved
    } from '$plugins/assistants/admin/api/assistantReviewClient';

    const {
        assistantId,
        field,
        label,
        value,
        flags,
        onFlagsChanged,
        selectable = true
    }: {
        assistantId: string;
        field: string;
        label: string;
        value: string;
        /** Flags for this field only, any order. */
        flags: AssistantFieldFlag[];
        /** Called after a flag is created, deleted, or its resolved state toggled, so the parent can refresh its list (and the approve-gate). */
        onFlagsChanged: () => void;
        /** Long-text fields (system prompt, description, ...) support selecting a passage; short/whole-value fields only support flagging the whole field. */
        selectable?: boolean;
    } = $props();
    const { __ } = useTranslator();

    let container = $state<HTMLDivElement>();
    let selectionText = $state('');
    let selectionPos = $state<{ x: number; y: number } | null>(null);
    let composing = $state(false);
    let comment = $state('');
    let submitting = $state(false);

    const segments = $derived.by(() => {
        type Segment = { text: string; flag: AssistantFieldFlag | null };
        const marks: { start: number; end: number; flag: AssistantFieldFlag }[] = [];

        for (const flag of flags) {
            if (!flag.excerpt) continue;
            const at = value.indexOf(flag.excerpt);
            if (at === -1) continue;
            marks.push({ start: at, end: at + flag.excerpt.length, flag });
        }
        marks.sort((a, b) => a.start - b.start);

        const result: Segment[] = [];
        let cursor = 0;
        for (const mark of marks) {
            if (mark.start < cursor) continue; // overlapping excerpt, skip
            if (mark.start > cursor) result.push({ text: value.slice(cursor, mark.start), flag: null });
            result.push({ text: value.slice(mark.start, mark.end), flag: mark.flag });
            cursor = mark.end;
        }
        if (cursor < value.length) result.push({ text: value.slice(cursor), flag: null });
        return result;
    });

    function onSelectionChange(): void {
        const selection = window.getSelection();
        if (!selection || selection.isCollapsed || !container) {
            selectionText = '';
            selectionPos = null;
            return;
        }
        const anchor = selection.anchorNode;
        const focus = selection.focusNode;
        if (!anchor || !focus || !container.contains(anchor) || !container.contains(focus)) {
            selectionText = '';
            selectionPos = null;
            return;
        }
        const text = selection.toString().trim();
        if (!text) {
            selectionText = '';
            selectionPos = null;
            return;
        }
        const range = selection.getRangeAt(0);
        const rect = range.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();
        selectionText = text;
        selectionPos = { x: rect.right - containerRect.left, y: rect.bottom - containerRect.top };
    }

    /** Opens the composer for the passage currently selected (set by {@link onSelectionChange}). */
    function startSelectionFlag(): void {
        composing = true;
        comment = '';
    }

    /** Opens the composer for the whole field, discarding any in-progress text selection. */
    function startWholeFieldFlag(): void {
        selectionText = '';
        selectionPos = null;
        window.getSelection()?.removeAllRanges();
        composing = true;
        comment = '';
    }

    function cancelFlag(): void {
        composing = false;
        comment = '';
        selectionText = '';
        selectionPos = null;
        window.getSelection()?.removeAllRanges();
    }

    async function submitFlag(): Promise<void> {
        if (!comment.trim()) return;
        submitting = true;
        try {
            await createAssistantFieldFlag(assistantId, field, comment.trim(), selectionText || null);
            onFlagsChanged();
            cancelFlag();
        } finally {
            submitting = false;
        }
    }

    async function toggleResolved(flag: AssistantFieldFlag): Promise<void> {
        await setAssistantFieldFlagResolved(flag.id, !flag.resolved);
        onFlagsChanged();
    }

    async function removeFlag(flag: AssistantFieldFlag): Promise<void> {
        await deleteAssistantFieldFlag(flag.id);
        onFlagsChanged();
    }
</script>

<div class="flaggable-field">
    <div class="field-header">
        <span class="field-label">{label}</span>
        {#if !composing}
            <Button variant="ghost" size="xs" onclick={startWholeFieldFlag}>
                <Flag01Icon size={14} />
                <!--{__('admin.flags.submit')}-->
            </Button>
        {/if}
    </div>
    {#if selectable}
        <div
            class="field-text"
            bind:this={container}
            onmouseup={onSelectionChange}
            onkeyup={onSelectionChange}
        >
            {#each segments as segment, i (i)}
                {#if segment.flag}
                    <mark class:resolved={segment.flag.resolved} title={segment.flag.comment}>{segment.text}</mark>
                {:else}
                    {segment.text}
                {/if}
            {/each}
            {#if selectionText && selectionPos && !composing}
                <button
                    type="button"
                    class="flag-trigger"
                    style={`left:${selectionPos.x}px; top:${selectionPos.y}px;`}
                    onmousedown={(event) => event.preventDefault()}
                    onclick={startSelectionFlag}
                >
                    <Flag01Icon size={14} />
                    {__('admin.flags.flag_selection')}
                </button>
            {/if}
        </div>
    {:else}
        <div class="field-value">{value || '—'}</div>
    {/if}

    {#if composing}
        <div class="flag-composer">
            {#if selectionText}
                <p class="excerpt-preview">"{selectionText}"</p>
            {/if}
            <Textarea
                bind:value={comment}
                ariaLabel={__('admin.flags.comment_label')}
                placeholder={__('admin.flags.comment_placeholder')}
            />
            <div class="composer-actions">
                <Button variant="ghost" size="sm" onclick={cancelFlag}>{__('admin.cancel')}</Button>
                <Button
                    variant="fill"
                    size="sm"
                    disabled={!comment.trim() || submitting}
                    onclick={submitFlag}
                >
                    {__('admin.flags.submit')}
                </Button>
            </div>
        </div>
    {/if}

    {#if flags.length}
        <ul class="flag-list">
            {#each flags as flag (flag.id)}
                <li class:resolved={flag.resolved}>
                    <Flag01Icon size={14} />
                    <div class="flag-body">
                        {#if flag.excerpt}<p class="excerpt">"{flag.excerpt}"</p>{/if}
                        <p class="comment">{flag.comment}</p>
                        <p class="meta">{flag.adminName} · {new Date(flag.createdAt).toLocaleString()}</p>
                    </div>
                    <div class="flag-actions">
                        <Button variant="stroke" size="sm" onclick={() => toggleResolved(flag)}>
                            {__(flag.resolved ? 'admin.flags.reopen' : 'admin.flags.resolve')}
                        </Button>
                        <Button variant="delete" size="sm" onclick={() => removeFlag(flag)}>
                            {__('admin.flags.remove')}
                        </Button>
                    </div>
                </li>
            {/each}
        </ul>
    {/if}
</div>

<style>
    .flaggable-field {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }
    .field-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2);
    }
    .field-label {
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }
    .field-value {
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
    }
    .field-text {
        position: relative;
        white-space: pre-wrap;
        border: var(--border);
        border-radius: var(--corner-md);
        padding: var(--space-3);
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        user-select: text;
        background: var(--color-surface);
    }
    mark {
        background: color-mix(in oklch, var(--color-warning) 30%, transparent);
        border-radius: 3px;
        padding: 0 2px;
    }
    mark.resolved {
        background: color-mix(in oklch, var(--color-text-muted) 20%, transparent);
        text-decoration: line-through;
    }
    .flag-trigger {
        position: absolute;
        transform: translate(-50%, var(--space-1));
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        border: var(--border);
        border-radius: var(--corner-full);
        background: var(--color-surface-raised);
        box-shadow: var(--elevation-2);
        padding: var(--space-1) var(--space-3);
        font-size: var(--font-size-xs);
        cursor: pointer;
        z-index: 1;
    }
    .flag-composer {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        border: var(--border);
        border-radius: var(--corner-md);
        padding: var(--space-3);
        background: var(--color-bg);
    }
    .excerpt-preview {
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        font-style: italic;
    }
    .composer-actions {
        display: flex;
        justify-content: flex-end;
        gap: var(--space-2);
    }
    .flag-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }
    .flag-list li {
        display: flex;
        align-items: flex-start;
        gap: var(--space-2);
        border: var(--border);
        border-radius: var(--corner-md);
        padding: var(--space-2) var(--space-3);
    }
    .flag-list li.resolved {
        opacity: 0.6;
    }
    .flag-actions {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        flex: 0 0 auto;
    }
    .flag-body {
        flex: 1;
        min-width: 0;
    }
    .flag-body p {
        margin: 0;
    }
    .flag-body .excerpt {
        font-size: var(--font-size-xs);
        font-style: italic;
        color: var(--color-text-muted);
    }
    .flag-body .comment {
        font-size: var(--font-size-sm);
    }
    .flag-body .meta {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        margin-top: var(--space-1);
    }
</style>
