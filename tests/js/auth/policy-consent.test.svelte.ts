import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {createPolicyConsent} from '../../../resources/js/plugins/auth/pages/policyConsentState.svelte.js';

// Stub scroll containers: only the three metrics the consent state reads.
const scrolledPartly = {scrollHeight: 1000, scrollTop: 200, clientHeight: 600};
const scrolledToTheEnd = {scrollHeight: 1000, scrollTop: 395, clientHeight: 600};
const fitsWithoutScrolling = {scrollHeight: 600, scrollTop: 0, clientHeight: 600};

test('the consent checkbox stays locked until the policy was scrolled to its end', () => {
    const consent = createPolicyConsent();
    assert.equal(consent.locked, true);
    assert.equal(consent.describedBy, 'policy-scroll-hint');

    consent.check(scrolledPartly);
    assert.equal(consent.locked, true, 'a partially read policy must not unlock consent');

    consent.check(scrolledToTheEnd);
    assert.equal(consent.locked, false);
    assert.equal(consent.describedBy, undefined, 'the scroll hint stops describing once unlocked');
});

test('a policy that fits without scrolling unlocks immediately', () => {
    const consent = createPolicyConsent();
    consent.check(fitsWithoutScrolling);
    assert.equal(consent.locked, false);
});

test('submitting without consent is refused and flags the required error', () => {
    const consent = createPolicyConsent();
    consent.check(scrolledToTheEnd);

    assert.equal(consent.submit(), false);
    assert.equal(consent.showRequiredError, true);
    assert.equal(consent.describedBy, 'policy-error');

    consent.consent = true;
    assert.equal(consent.submit(), true);
    assert.equal(consent.showRequiredError, false);
});

test('reset starts a fresh reading session', () => {
    const consent = createPolicyConsent();
    consent.check(scrolledToTheEnd);
    consent.consent = true;
    assert.equal(consent.submit(), true);

    consent.reset();
    assert.equal(consent.locked, true);
    assert.equal(consent.consent, false);
    assert.equal(consent.showRequiredError, false);
    assert.equal(consent.describedBy, 'policy-scroll-hint');
});
