import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { AdminUsageSchema } from '../../../resources/js/plugins/admin/schemas/resources/admin-usage.schema.js';

test('usage rows accept the integer labels that grouping by user returns', () => {
    const row = AdminUsageSchema.parse({ id: '42', label: 42, requests: '3', prompt_tokens: '10', completion_tokens: '5' });
    assert.equal(row.label, '42');
    assert.equal(row.requests, 3);
    assert.equal(AdminUsageSchema.parse({ id: 'gpt', label: 'gpt', requests: 0, prompt_tokens: 0, completion_tokens: 0 }).label, 'gpt');
});
