/**
 * Consent-gate state of the registration policy dialog: the checkbox stays locked
 * until the policy's scroll container was scrolled to its end (or fits without
 * scrolling), one reading session at a time.
 *
 * Extracted from the dialog component so the gate logic is testable without a DOM —
 * {@link check} only reads three numeric element properties, so a plain stub object
 * with `scrollHeight` / `scrollTop` / `clientHeight` is enough to drive it.
 */
export function createPolicyConsent() {
    let scrolledToEnd = $state(false);
    let consent = $state(false);
    let showRequiredError = $state(false);

    return {
        /** True once the policy region was scrolled to its end (or fits without scrolling). */
        get scrolledToEnd() {
            return scrolledToEnd;
        },
        /** True while the policy still has to be scrolled; the checkbox is disabled then. */
        get locked() {
            return !scrolledToEnd;
        },
        get consent() {
            return consent;
        },
        set consent(value: boolean) {
            consent = value;
        },
        /** True after a submit without consent, until consent is given or the session resets. */
        get showRequiredError() {
            return showRequiredError;
        },
        /** The checkbox's aria-describedby: the required-error and/or the hidden scroll hint. */
        get describedBy(): string | undefined {
            return [
                showRequiredError ? 'policy-error' : null,
                scrolledToEnd ? null : 'policy-scroll-hint'
            ].filter((id): id is string => id !== null).join(' ') || undefined;
        },
        /**
         * Re-checks whether `$el` is scrolled to its end. Stub-friendly on purpose —
         * anything with the three scroll metrics works.
         */
        check(el: {scrollHeight: number; scrollTop: number; clientHeight: number}): void {
            scrolledToEnd = el.scrollHeight - el.scrollTop - el.clientHeight < 8;
        },
        /** Starts a fresh reading session: re-locks the checkbox and clears a prior error. */
        reset(): void {
            scrolledToEnd = false;
            consent = false;
            showRequiredError = false;
        },
        /**
         * Returns whether the consent may be recorded. Flags the required-error
         * (and reports `false`) when the checkbox is unchecked.
         */
        submit(): boolean {
            if (!consent) {
                showRequiredError = true;
                return false;
            }
            showRequiredError = false;
            return true;
        }
    };
}
