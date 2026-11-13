import assert from 'node:assert/strict';
import {test} from 'node:test';
import {setHawkiApp} from '../../../resources/js/legacy/legacy.js';
import {
    AssistantReviewWireSchema,
    ReviewTargetWireSchema,
    DRAFT_VERSION,
    toQueueRow
} from '../../../resources/js/plugins/assistants/admin/api/schemas.js';
import {
    listReviewQueue,
    getReviewTarget,
    submitReviewDecision,
    rollbackAssistant,
    mayDecideReviews
} from '../../../resources/js/plugins/assistants/admin/api/reviewClient.js';
import {mayReviewAssistants, probeReviewPermission} from '../../../resources/js/plugins/assistants/admin/reviewPermission.svelte.js';

/**
 * Fake RestApi: records every call and answers from a mutable handler map.
 * Installed once per process (`setHawkiApp` refuses a second set); tests
 * swap the handlers they need. The review client resolves its app through
 * the legacy global fallback (`setHawkiApp`), which is exactly how it
 * behaves outside a component tree.
 */
const handlers = {};
const calls = [];
setHawkiApp({
    restApi: {
        getResourceCollection: async (resourceType, options) => {
            calls.push({method: 'getResourceCollection', resourceType, options});
            return handlers.getResourceCollection(options);
        },
        getResource: async (resourceType, id, options) => {
            calls.push({method: 'getResource', resourceType, id, options});
            return handlers.getResource(id, options);
        },
        updateResource: async (resourceType, id, attributes, options) => {
            calls.push({method: 'updateResource', resourceType, id, attributes, options});
            return handlers.updateResource?.(resourceType, id, attributes) ?? {};
        },
        postToResourceAction: async (resourceType, action, body) => {
            calls.push({method: 'postToResourceAction', resourceType, action, body});
            return handlers.postToResourceAction?.(resourceType, action, body) ?? {};
        },
        getFromResourceAction: async (resourceType, action, options) => {
            calls.push({method: 'getFromResourceAction', resourceType, action, options});
            return handlers.getFromResourceAction?.(action);
        }
    }
});

/** Replaces the handlers and clears the call log; returns the log. */
function installRestApi(next) {
    for (const key of Object.keys(handlers)) {
        delete handlers[key];
    }
    Object.assign(handlers, next);
    calls.length = 0;
    return calls;
}

test('schemas: a review row parses with its assistant include', () => {
    const review = AssistantReviewWireSchema.parse({
        id: '7',
        status: 'pending',
        reason: null,
        created_at: '2026-11-09T10:00:00Z',
        updated_at: '2026-11-09T10:00:00Z',
        assistant: {
            id: '42',
            name: 'Tutor',
            handle: '@tutor',
            release_stage: 'private',
            requested_release_stage: 'organizational'
        }
    });

    const row = toQueueRow(review);
    assert.equal(row.reviewId, '7');
    assert.equal(row.assistantId, '42');
    assert.equal(row.status, 'pending');
    assert.equal(row.requestedStage, 'organizational');
    assert.equal(row.name, 'Tutor');
});

test('schemas: a review without the assistant include still maps', () => {
    const row = toQueueRow(AssistantReviewWireSchema.parse({id: '8', status: 'approved'}));
    assert.equal(row.assistantId, '');
    assert.equal(row.requestedStage, null);
});

test('schemas: the review target parses with review, versions, creator and relation summaries', () => {
    const target = ReviewTargetWireSchema.parse({
        id: '42',
        name: 'Tutor',
        handle: '@tutor',
        description: 'd',
        detail_description: 'dd',
        system_prompt: 's',
        greeting: 'g',
        model: 'gpt-4',
        max_tokens: 4096,
        temp: 0.7,
        top_p: 1,
        allow_remix: false,
        allow_model_select: true,
        release_stage: 'organizational',
        assistant_review: {id: '7', status: 'pending', reason: null},
        assistant_versions: [
            {id: 'a', text: '', version: 0, changed_keys: ['name'], created_at: '2026-11-09T10:00:00Z', updated_at: '2026-11-09T10:00:00Z'},
            {id: 'b', text: 'Initial', version: 1, changed_keys: null, created_at: '2026-11-08T10:00:00Z', updated_at: '2026-11-08T10:00:00Z'}
        ],
        creator: {id: '5', display_name: 'Jane'},
        assistant_category: {text: 'assistants.categories.academic_writing'},
        assistant_tags: [{text: 'essay'}, {text: 'thesis'}],
        assistant_user_prompts: [{text: 'Help me plan.'}],
        assistant_setting_values: [{value: 'en', setting: {key: 'language'}}],
        ai_tools: [{name: 'Web search'}]
    });

    assert.equal(target.assistant_review.id, '7');
    assert.equal(target.assistant_versions.length, 2);
    // The draft row arrives numeric on the wire; the page detects it by its
    // stringified version.
    assert.equal(String(target.assistant_versions[0].version), DRAFT_VERSION);
    assert.equal(target.creator.display_name, 'Jane');
    assert.equal(target.detail_description, 'dd');
    assert.equal(target.assistant_category?.text, 'assistants.categories.academic_writing');
    assert.equal(target.assistant_setting_values?.[0]?.setting?.key, 'language');
    assert.equal(target.ai_tools?.[0]?.name, 'Web search');
});

test('client: listReviewQueue queries by status with the assistant include', async () => {
    const calls = installRestApi({
        getResourceCollection: () =>
            Object.assign(
                [
                    {id: '7', status: 'pending', assistant: {id: '42', name: 'Tutor', handle: '@tutor', release_stage: 'private'}}
                ],
                {_pagination: {itemCount: 11, page: 1, pages: 1, pageSize: 1, hasNextPage: false, hasPreviousPage: false}}
            )
    });

    const page = await listReviewQueue('pending', {number: 2, size: 25});

    const call = calls[0];
    assert.equal(call.resourceType, 'assistant-reviews');
    assert.deepEqual(call.options.query.filter, {status: 'pending'});
    assert.deepEqual(call.options.query.include, ['assistant']);
    assert.deepEqual(call.options.query.page, {number: 2, size: 25});
    assert.deepEqual(call.options.query.sort, ['-created_at']);

    assert.equal(page.rows.length, 1);
    assert.equal(page.rows[0].name, 'Tutor');
    assert.equal(page.total, 11);
});

test('client: a null status omits the filter — the All tab', async () => {
    const calls = installRestApi({
        getResourceCollection: () => Object.assign([], {_pagination: {itemCount: 0}})
    });

    await listReviewQueue(null, {number: 1, size: 25});

    const query = calls[0].options.query;
    assert.equal('filter' in query, false);
    assert.deepEqual(query.include, ['assistant']);
    assert.deepEqual(query.sort, ['-created_at']);
});

test('client: getReviewTarget includes review, versions, creator and relation summaries', async () => {
    const calls = installRestApi({
        getResource: () => ({id: '42', name: 'Tutor'})
    });

    const target = await getReviewTarget('42');

    const call = calls[0];
    assert.equal(call.resourceType, 'assistants');
    assert.equal(call.id, '42');
    assert.deepEqual(call.options.query.include, [
        'assistant_review',
        'assistant_versions',
        'creator',
        'assistant_category',
        'assistant_avatar',
        'assistant_attachments',
        'assistant_tags',
        'assistant_user_prompts',
        'assistant_setting_values.setting',
        'ai_tools'
    ]);
    assert.equal(target.name, 'Tutor');
});

test('client: submitReviewDecision patches the review resource', async () => {
    const calls = installRestApi({});

    await submitReviewDecision('7', {status: 'denied', reason: 'Not ready'});

    const call = calls[0];
    assert.equal(call.method, 'updateResource');
    assert.equal(call.resourceType, 'assistant-reviews');
    assert.equal(call.id, '7');
    assert.deepEqual(call.attributes, {status: 'denied', reason: 'Not ready'});
});

test('client: submitReviewDecision omits an empty reason', async () => {
    const calls = installRestApi({});

    await submitReviewDecision('7', {status: 'approved'});

    assert.deepEqual(calls[0].attributes, {status: 'approved'});
});

test('client: rollbackAssistant posts the action with the version', async () => {
    const calls = installRestApi({});

    await rollbackAssistant('42', 1.0);

    const call = calls[0];
    assert.equal(call.method, 'postToResourceAction');
    assert.equal(call.resourceType, 'assistants');
    assert.equal(call.action, '42/actions/rollback');
    assert.deepEqual(call.body, {
        data: {type: 'assistants', id: '42', attributes: {version: 1.0}}
    });
});

test('client: mayDecideReviews probes the class-level updateAny verdict', async () => {
    const calls = installRestApi({
        getFromResourceAction: () => ({data: {updateAny: 'ALLOWED'}})
    });

    assert.equal(await mayDecideReviews(), true);

    const call = calls[0];
    assert.equal(call.resourceType, 'assistant-reviews');
    assert.equal(call.action, 'actions/permissions');
});

test('client: a denied updateAny verdict maps to false', async () => {
    installRestApi({getFromResourceAction: () => ({data: {updateAny: 'DENIED'}})});

    assert.equal(await mayDecideReviews(), false);
});

test('store: probing stores the verdict once; unauthenticated apps never probe', async () => {
    const calls = installRestApi({
        getFromResourceAction: () => ({data: {updateAny: 'ALLOWED'}})
    });
    const tick = () => new Promise(resolve => setImmediate(resolve));

    assert.equal(mayReviewAssistants(), null);

    probeReviewPermission({connection: {isAuthenticated: false}} as never);
    await tick();
    assert.equal(calls.length, 0);
    assert.equal(mayReviewAssistants(), null);

    probeReviewPermission({connection: {isAuthenticated: true}} as never);
    await tick();
    assert.equal(calls.length, 1);
    assert.equal(mayReviewAssistants(), true);

    // Idempotent per session: a second probe does not refetch.
    probeReviewPermission({connection: {isAuthenticated: true}} as never);
    await tick();
    assert.equal(calls.length, 1);
});
