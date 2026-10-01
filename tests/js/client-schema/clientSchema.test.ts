import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import type { RestApi } from '../../../resources/js/kernel/api/RestApi.js';
import {
    ClientSchemaDocumentSchema,
    type ClientSchemaDocument
} from '../../../resources/js/kernel/clientSchema/clientSchemaDocument.js';
import {
    actionUrl,
    attributeDefault,
    enumValues,
    fetchClientSchema,
    invalidateClientSchema,
    isEndpointAllowed,
    isWritableAttribute,
    relationship,
    requiredAttributes,
    writableOn
} from '../../../resources/js/kernel/clientSchema/clientSchemaClient.js';

const fixture = {
    version: '1.0',
    generatedAt: '2026-09-29T12:00:00+00:00',
    resources: {
        assistants: {
            type: 'assistants',
            displayName: 'Assistants',
            endpoints: {
                list: { method: 'GET', url: '/api/hawki/v1/assistants', allowed: true },
                create: { method: 'POST', url: '/api/hawki/v1/assistants', allowed: true },
                read: { method: 'GET', url: '/api/hawki/v1/assistants/{id}', allowed: true },
                update: { method: 'PATCH', url: '/api/hawki/v1/assistants/{id}', allowed: true },
                delete: { method: 'DELETE', url: '/api/hawki/v1/assistants/{id}', allowed: false }
            },
            attributes: {
                name: {
                    type: 'string',
                    required: true,
                    constraints: { maxLength: 255 },
                    writable_on: [{ method: 'PATCH', path: '/api/hawki/v1/assistants/{id}' }],
                    default: ''
                },
                release_stage: {
                    type: 'enum',
                    constraints: { values: ['draft', 'private'] }
                },
                is_favorite: {
                    type: 'boolean',
                    readOnly: true,
                    writable_on: [
                        { method: 'POST', path: '/api/hawki/v1/assistants/{id}/actions/favorite' },
                        { method: 'DELETE', path: '/api/hawki/v1/assistants/{id}/actions/favorite' }
                    ]
                }
            },
            schema: {
                type: 'object',
                properties: {
                    name: { type: 'string', maxLength: 255, default: '' },
                    release_stage: { type: 'string', enum: ['draft', 'private'] },
                    max_tokens: { type: 'integer', minimum: 0, default: 0 }
                },
                required: ['name']
            },
            relationships: {
                assistant_category: { cardinality: 'toOne', type: 'assistant-categories' },
                assistant_tags: {
                    cardinality: 'toMany',
                    type: 'assistant-tags',
                    writable_on: [
                        { method: 'POST', path: '/api/hawki/v1/assistants/{id}/relationships/assistant-tags' }
                    ]
                }
            },
            actions: {
                favorite: {
                    method: 'POST',
                    url: '/api/hawki/v1/assistants/{id}/actions/favorite',
                    allowed: true
                }
            },
            filters: [{ name: 'filter[name]', type: 'string' }],
            sortable: ['name'],
            includable: ['assistant_category']
        },
        'assistant-reviews': {
            type: 'assistant-reviews',
            displayName: 'Assistant Reviews',
            attributes: {},
            relationships: {},
            actions: {}
        }
    }
};

const document: ClientSchemaDocument = ClientSchemaDocumentSchema.parse(fixture);

test('document schema parses resources with typed schema blocks', () => {
    assert.equal(document.version, '1.0');
    const assistants = document.resources.assistants;
    assert.equal(assistants.displayName, 'Assistants');
    assert.deepEqual(assistants.schema?.properties.release_stage.enum, ['draft', 'private']);
    assert.deepEqual(assistants.schema?.required, ['name']);
    assert.deepEqual(assistants.attributes.name.writable_on, [
        { method: 'PATCH', path: '/api/hawki/v1/assistants/{id}' }
    ]);
});

test('endpoint flags reflect the policy-derived allowed state', () => {
    assert.equal(isEndpointAllowed(document, 'assistants', 'create'), true);
    assert.equal(isEndpointAllowed(document, 'assistants', 'delete'), false);
    assert.equal(isEndpointAllowed(document, 'assistant-reviews', 'list'), false);
});

test('attribute writability follows the advertised write paths', () => {
    assert.equal(isWritableAttribute(document, 'assistants', 'name'), true);
    assert.equal(isWritableAttribute(document, 'assistants', 'name', 'POST'), false);
    assert.equal(isWritableAttribute(document, 'assistants', 'release_stage'), false);
    assert.equal(isWritableAttribute(document, 'assistants', 'is_favorite', 'post'), true);
    assert.equal(isWritableAttribute(document, 'assistants', 'is_favorite'), false);
    assert.equal(isWritableAttribute(document, 'unknown-resource', 'name'), false);
});

test('writableOn returns the paths or undefined when none are advertised', () => {
    assert.deepEqual(writableOn(document, 'assistants', 'name'), [
        { method: 'PATCH', path: '/api/hawki/v1/assistants/{id}' }
    ]);
    assert.equal(writableOn(document, 'assistants', 'release_stage'), undefined);
    assert.equal(writableOn(document, 'assistants', 'missing'), undefined);
});

test('enum values, required attributes and defaults come from the JSON-Schema block', () => {
    assert.deepEqual(enumValues(document, 'assistants', 'release_stage'), ['draft', 'private']);
    assert.equal(enumValues(document, 'assistants', 'name'), undefined);
    assert.deepEqual(requiredAttributes(document, 'assistants'), ['name']);
    assert.deepEqual(requiredAttributes(document, 'assistant-reviews'), []);
    assert.equal(attributeDefault(document, 'assistants', 'name'), '');
    assert.equal(attributeDefault(document, 'assistants', 'release_stage'), undefined);
});

test('relationships expose cardinality, inverse type and write paths', () => {
    const category = relationship(document, 'assistants', 'assistant_category');
    assert.equal(category?.cardinality, 'toOne');
    assert.equal(category?.type, 'assistant-categories');
    const tags = relationship(document, 'assistants', 'assistant_tags');
    assert.equal(tags?.cardinality, 'toMany');
    assert.equal(tags?.writable_on?.length, 1);
    assert.equal(relationship(document, 'assistants', 'missing'), undefined);
});

test('action urls resolve from the document', () => {
    assert.equal(
        actionUrl(document, 'assistants', 'favorite'),
        '/api/hawki/v1/assistants/{id}/actions/favorite'
    );
    assert.equal(actionUrl(document, 'assistants', 'missing'), undefined);
});

test('client schema is fetched once per session and invalidated on demand', async () => {
    invalidateClientSchema();

    let calls = 0;
    const fetched: ClientSchemaDocument = { ...document };
    const restApi = {
        getClientSchema: async (): Promise<ClientSchemaDocument> => {
            calls++;
            return fetched;
        }
    } as unknown as RestApi;

    const first = await fetchClientSchema(restApi);
    const second = await Promise.all([fetchClientSchema(restApi), fetchClientSchema(restApi)]);

    assert.equal(calls, 1);
    assert.equal(first, fetched);
    assert.ok(second.every((result) => result === fetched));

    invalidateClientSchema();
    await fetchClientSchema(restApi);
    assert.equal(calls, 2);
});

test('a failed fetch drops the cache so the next call retries', async () => {
    invalidateClientSchema();

    let calls = 0;
    const restApi = {
        getClientSchema: async (): Promise<ClientSchemaDocument> => {
            calls++;
            if (calls === 1) {
                throw new Error('network down');
            }
            return document;
        }
    } as unknown as RestApi;

    await assert.rejects(() => fetchClientSchema(restApi), /network down/);
    const recovered = await fetchClientSchema(restApi);
    assert.equal(recovered, document);
    assert.equal(calls, 2);
});
