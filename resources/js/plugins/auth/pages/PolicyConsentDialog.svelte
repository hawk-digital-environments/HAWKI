<!--
  @component The usage-policy reading dialog of the registration: the scrollable policy
  document, the consent checkbox that unlocks only after the text was scrolled to its
  end (or fits without scrolling), and the decline/confirm actions.

  The dialog owns one reading session: opening it resets the scroll and consent state
  (see policyConsentState.svelte.ts). Acceptance is reported through `onAccept` after
  the dialog closed itself; declining through `onDecline`. Unchecking a previously
  checked consent box is reported through `onConsentRevoked` so the page can revoke an
  earlier acceptance.
-->
<script lang="ts">
    import type {z} from 'zod';
    import {tick} from 'svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {RegistrationPolicySchema} from '../schemas/resources/auth.schema.js';
    import {createPolicyConsent} from './policyConsentState.svelte.js';

    type Policy = NonNullable<z.infer<typeof RegistrationPolicySchema>['policy']>;

    interface Props {
        policy: Policy;
        /** Whether the dialog is open; the dialog closes itself on decline/confirm. */
        open: boolean;
        onAccept: () => void;
        onDecline: () => void;
        /** Called when a previously checked consent box is unchecked again. */
        onConsentRevoked: () => void;
        /** Returns focus to the page when the dialog closes. */
        returnFocus: () => void;
    }

    let {policy, open = $bindable(false), onAccept, onDecline, onConsentRevoked, returnFocus}: Props = $props();
    const {__} = useTranslator();
    const consent = createPolicyConsent();
    let consentInput = $state<HTMLInputElement | null>(null);
    let policyBody = $state<HTMLDivElement | null>(null);
    let policyContent = $state<HTMLDivElement | null>(null);
    let policyObserver: ResizeObserver | null = null;

    // The body mounts with each opening; a reopened reading session starts fresh: the
    // policy must be re-scrolled and re-confirmed. The markdown renders asynchronously,
    // so the "fits without scrolling" state is only trustworthy once the content has
    // settled — a ResizeObserver re-checks on every growth spurt and locks the checkbox
    // back if the text got taller.
    $effect(() => {
        if (!open || !policyBody) return;
        consent.reset();
        consent.check(policyBody);
        policyObserver?.disconnect();
        if (policyContent) {
            policyObserver = new ResizeObserver(() => policyBody && consent.check(policyBody));
            policyObserver.observe(policyContent);
        }
        return () => policyObserver?.disconnect();
    });

    async function confirm() {
        if (!consent.submit()) {
            await tick();
            consentInput?.focus();
            return;
        }
        open = false;
        onAccept();
    }

    function decline() {
        open = false;
        onDecline();
    }
</script>

<Dialog
    bind:open
    title={__('ui.auth.register.policyTitle')}
    description={__('ui.auth.register.policyDescription')}
    contentProps={{
        class: 'registration-policy-dialog',
        onCloseAutoFocus: (event) => {
            event.preventDefault();
            returnFocus();
        }
    }}
    footerProps={{class: 'registration-policy-footer'}}
>
    <!-- svelte-ignore a11y_no_noninteractive_tabindex (The scrollable policy must be reachable by keyboard.) -->
    <div
        class="policy-document"
        lang={policy.locale.replace('_', '-')}
        role="region"
        aria-label={__('ui.auth.register.policyTitle')}
        tabindex="0"
        bind:this={policyBody}
        onscroll={(event) => consent.check(event.currentTarget)}
    >
        <div class="policy-scroll-content" bind:this={policyContent}>
            <Markdown message={policy.text} headingBaseLevel={3}/>
        </div>
    </div>
    {#snippet footer()}
        <form class="policy-confirmation" onsubmit={(event) => { event.preventDefault(); void confirm(); }}>
            <Tooltip tooltip={consent.scrolledToEnd ? '' : __('ui.auth.register.policyScrollHint')} disabled={consent.scrolledToEnd} focusable={false} delayDuration={500}>
                {#snippet children({props})}
                    <label class="consent" class:locked={consent.locked} {...props}>
                        <input
                            type="checkbox"
                            bind:this={consentInput}
                            bind:checked={consent.consent}
                            disabled={consent.locked}
                            onchange={() => { if (!consent.consent) onConsentRevoked(); }}
                            aria-invalid={consent.showRequiredError || undefined}
                            aria-describedby={consent.describedBy}
                        />
                        <span>{__('ui.auth.register.acceptPolicy')}</span>
                    </label>
                {/snippet}
            </Tooltip>
            <!-- The lock reason is hover-only as a tooltip; this hidden twin carries it to
                 screen reader and keyboard users through the checkbox's aria-describedby. -->
            <span class="vh" id="policy-scroll-hint">{__('ui.auth.register.policyScrollHint')}</span>
            {#if consent.showRequiredError}<p id="policy-error" class="auth-error" role="alert">{__('ui.auth.register.policyRequired')}</p>{/if}
            <div class="policy-actions">
                <Button type="button" variant="stroke" onclick={decline}>{__('ui.auth.register.declinePolicy')}</Button>
                <Button type="submit" variant="fill" disabled={!consent.consent}>{__('ui.auth.register.confirmPolicy')}</Button>
            </div>
        </form>
    {/snippet}
</Dialog>

<style>
    :global(.registration-policy-dialog.registration-policy-dialog) {
        box-sizing: border-box;
        width: calc(100% - 2rem);
        max-width: 48rem;
        max-height: calc(100dvh - 3rem);
        gap: var(--space-5);
        overflow: hidden;
        grid-template-rows: auto minmax(0, 1fr) auto;
    }
    :global(.registration-policy-dialog .dialog-header) {
        padding-right: var(--space-6);
        gap: var(--space-2);
    }
    :global(.registration-policy-dialog .dialog-title) {
        font-size: 1.375rem;
        line-height: 1.3;
    }
    :global(.registration-policy-dialog .dialog-description) {
        font-size: 0.9375rem;
        line-height: 1.5;
    }
    .policy-document {
        padding-right: var(--space-4);
        color: var(--color-text);
        overflow-y: auto;
        min-height: 0;
        scrollbar-gutter: stable;
        overscroll-behavior: contain;
    }
    .policy-scroll-content {
        display: block;
    }
    .policy-document :global(.markstream-svelte) {
        font-size: 1rem;
        line-height: 1.65;
        color: inherit;
    }
    .policy-document :global(.heading-node.heading-1) {
        font-size: 1.5rem;
        line-height: 1.3;
    }
    .policy-document :global(.heading-node.heading-2) {
        font-size: 1.25rem;
        line-height: 1.4;
    }
    .policy-document :global(.heading-node.heading-3) {
        font-size: 1.125rem;
    }
    .policy-document :global(.node-content:first-child .heading-node) {
        margin-top: 0;
    }
    .policy-document:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: -2px;
    }
    :global(.registration-policy-footer) {
        padding-top: var(--space-5);
        border-top: var(--border);
    }
    .policy-confirmation {
        display: grid;
        gap: var(--space-4);
        width: 100%;
    }
    .policy-confirmation .auth-error {
        margin: 0;
        color: var(--color-error);
        font-size: 0.9375rem;
        line-height: 1.5;
    }
    .policy-actions {
        display: flex;
        justify-content: flex-end;
        gap: var(--space-3);
    }
    .consent {
        display: flex;
        gap: var(--space-3);
        align-items: start;
        font-size: 0.9375rem;
        line-height: 1.5;
        cursor: pointer;
    }
    .consent input {
        flex-shrink: 0;
        width: 1.125rem;
        height: 1.125rem;
        margin: 0.125rem 0 0;
        accent-color: var(--color-accent-fill);
    }
    .consent input:focus-visible {
        outline: 2px solid var(--color-focus-ring);
        outline-offset: 2px;
    }
    .consent.locked {
        color: var(--color-text-disabled);
        cursor: not-allowed;
    }
    .consent.locked input {
        appearance: none;
        cursor: not-allowed;
        background-color: var(--color-disabled-bg);
        border: 1px solid var(--color-text-disabled);

    }
    /* Visually hidden but available to screen readers (see the policy-scroll-hint span). */
    .vh {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        border: 0;
        overflow: hidden;
        clip: rect(0 0 0 0);
        clip-path: inset(50%);
        white-space: nowrap;
    }
    @media (--bp-xs) {
        :global(.registration-policy-dialog.registration-policy-dialog) {
            max-height: calc(100dvh - 1rem);
            width: calc(100% - 1rem);
            padding: var(--space-4);
            gap: var(--space-4);
        }
        .policy-document {
            padding-right: var(--space-2);
        }
        .policy-actions {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>
