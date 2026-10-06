import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { createDraft, prepareValues } from '../../../resources/js/plugins/admin/form.js';
import { editorSchema } from '../../../resources/js/plugins/admin/forms/schemas.js';
import { AdminFieldSchema } from '../../../resources/js/plugins/admin/schemas/admin-content.js';
import { svgPreview } from '../../../resources/js/plugins/admin/providerIcons.js';

const fields = [AdminFieldSchema.parse({ key: 'icon', type: 'provider-icon' })];

test('a provider icon can be left empty, selected, preserved and removed', () => {
    assert.deepEqual(createDraft(fields, null), { icon: null });
    const icon = { source: 'svgl', svgl_id: 12, title: 'Firefox' };
    const row = { id: '1', icon };
    const schema = editorSchema('providers', fields, row);
    assert.deepEqual(prepareValues(fields, schema.parse(createDraft(fields, row))), { values: { icon }, errors: {} });
    assert.deepEqual(prepareValues(fields, schema.parse({ icon: null })), { values: { icon: null }, errors: {} });
});

test('SVG previews encode Unicode and markup as an image data URL', () => {
    const svg = '<svg xmlns="http://www.w3.org/2000/svg"><title>Grün &amp; weiß</title></svg>';
    const preview = svgPreview(svg)!;
    assert.equal(decodeURIComponent(preview.slice('data:image/svg+xml,'.length)), svg);
    assert.equal(svgPreview(null), undefined);
});
