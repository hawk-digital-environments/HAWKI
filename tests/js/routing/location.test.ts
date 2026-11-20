import assert from 'node:assert/strict';
import { test } from 'node:test';
import { joinLocation, splitLocation } from '../../../resources/js/components/ui/routing/logistics/normalizePath.js';

test('splitLocation separates and normalizes the path, keeping query and fragment verbatim', () => {
    assert.deepEqual(splitLocation('/admin/models/?provider_id=open%20ai&x=1#row-3'), {
        path: '/admin/models',
        query: 'provider_id=open%20ai&x=1',
        hash: 'row-3'
    });
    assert.deepEqual(splitLocation('/chat'), { path: '/chat', query: '', hash: '' });
    assert.deepEqual(splitLocation(''), { path: '/', query: '', hash: '' });
    assert.deepEqual(splitLocation(undefined), { path: '/', query: '', hash: '' });
    // A `?` inside the fragment belongs to the fragment.
    assert.deepEqual(splitLocation('/a#b?c=1'), { path: '/a', query: '', hash: 'b?c=1' });
});

test('joinLocation inverts splitLocation and drops empty parts', () => {
    for (const location of ['/admin/models?provider_id=x#row', '/chat', '/a#b', '/a?b=1']) {
        assert.equal(joinLocation(splitLocation(location)), location);
    }
    assert.equal(joinLocation({ path: '/a', query: '', hash: '' }), '/a');
});
