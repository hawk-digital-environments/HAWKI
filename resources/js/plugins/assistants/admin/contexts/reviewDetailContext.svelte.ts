import {createHmrSafeContext} from '$lib/utils/hmrSafeContext.js';
import {getReviewTarget, submitReviewDecision} from '../api/reviewClient.js';
import type {ReviewTargetWire} from '../api/schemas.js';
import {ApiError} from '$plugins/assistants/api/errors.js';
import type {ToastContext} from '$lib/components/ui/toast/ToastContext.svelte';
import type {RouterHandle} from '$lib/components/ui/routing/index.js';
import type {Translator} from '$lib/kernel/localization/translator.js';

/**
 * The review detail's shared state, owned by the `/:assistantId` layout so
 * both step pages (Changes, Review) and the step footer see one fetch and
 * one decision flow: the reason text and `decide()` live here because the
 * primary Approve action sits in the footer while the reason field sits in
 * the Review step's content. Created via {@link createReviewDetailContext}
 * in the layout; consumers read it through {@link useReviewDetailContext}.
 */
export class ReviewDetailContext {
    public readonly target = $state<{value: ReviewTargetWire | null}>({value: null});
    public loading = $state(true);
    public error = $state<string | null>(null);
    public forbidden = $state(false);

    /** The reviewer's reason; required for denials and revision requests. */
    public reason = $state('');
    /** Set when a submit was attempted without the required reason. */
    public reasonError = $state(false);
    public deciding = $state(false);

    /** Reason input from the Review step; typing clears the required error. */
    public setReason(value: string): void {
        this.reason = value;
        this.reasonError = false;
    }

    /** The decision card picked on the Review step; null until chosen. */
    public selectedDecision = $state<'approved' | 'denied' | 'needs_revision' | null>(null);

    public constructor(
        private readonly toast: ToastContext,
        private readonly translate: Translator['translate'],
        private readonly router: RouterHandle
    ) {
    }

    public async load(assistantId: string, signal?: AbortSignal): Promise<void> {
        this.loading = true;
        this.error = null;
        this.forbidden = false;
        try {
            this.target.value = await getReviewTarget(assistantId, signal);
        } catch (err) {
            if (err instanceof DOMException && err.name === 'AbortError') return;
            const apiError = ApiError.from(err);
            if (apiError.status === 403) {
                this.forbidden = true;
            } else {
                this.error = apiError.userMessage;
            }
        } finally {
            this.loading = false;
        }
    }

    /** Submit a decision; on success the flow returns to the queue. */
    public async decide(status: 'approved' | 'denied' | 'needs_revision'): Promise<void> {
        const review = this.target.value?.assistant_review;
        if (!review) return;
        this.deciding = true;
        try {
            await submitReviewDecision(review.id, {status, reason: this.reason.trim() || undefined});
            this.toast.success(this.translate(`assistants.admin.detail.toast.${status}`));
            await this.router.goToRoute('admin.assistants.index');
        } catch (err) {
            this.toast.error(`${this.translate('assistants.admin.detail.toast.failed')} ${ApiError.from(err).userMessage}`);
        } finally {
            this.deciding = false;
        }
    }

    /** Execute the picked decision card; denials and revision requests need a reason. */
    public async submit(): Promise<void> {
        if (null === this.selectedDecision) return;
        if ('approved' !== this.selectedDecision && '' === this.reason.trim()) {
            // The Review step's reason field shows the inline required error
            // and takes focus (see ReviewDecide).
            this.reasonError = true;
            return;
        }

        await this.decide(this.selectedDecision);
    }
}

const [get, set] = createHmrSafeContext<ReviewDetailContext>('hawki.assistants.review-detail');

/** Returns the review detail state published by the `/:assistantId` layout. */
export function useReviewDetailContext(): ReviewDetailContext {
    const context = get();
    if (!context) {
        throw new Error('No ReviewDetailContext found in the Svelte context tree.');
    }

    return context;
}

export function createReviewDetailContext(
    toast: ToastContext,
    translate: Translator['translate'],
    router: RouterHandle
): ReviewDetailContext {
    const context = new ReviewDetailContext(toast, translate, router);
    set(context);

    return context;
}

/**
 * The review currently open in the detail flow. Module state like the
 * builder's `builderProgress`: surfaces outside the layout's subtree (the
 * admin sidebar's step list) need the id for navigation without reaching
 * into the context tree.
 */
export const currentReview = $state<{assistantId: string | null}>({assistantId: null});
