import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { accentTokens, applyBrandTheme } from '../../../resources/js/plugins/admin/brandTheme.js';

const root = () => {
    const values = new Map<string, string>();
    return { values, style: { setProperty: (name: string, value: string) => void values.set(name, value) } };
};

test('accent tokens match the server (BrandThemeTest.php pins the same numbers)', () => {
    assert.deepEqual(accentTokens('#FF0000'), { hue: 29.2, chroma: 1 });
    assert.equal(accentTokens('#808080').chroma, 0);
    // The default brand color (config/hawki.php) must reproduce the built-in ramp exactly.
    assert.deepEqual(accentTokens('#1139d7'), { hue: 265, chroma: 1 });
});

test('the preview applies a color, keeps the accent while one is half-typed and returns to the defaults', () => {
    const target = root();
    applyBrandTheme({ accentColor: '#ff0000', cornerStyle: 'square' }, target as never);
    assert.equal(target.values.get('--accent-hue'), '29.2');
    assert.equal(target.values.get('--corner-lg'), '0');
    assert.equal(target.values.get('--corner-control'), '0');
    applyBrandTheme({ accentColor: '#ff0', cornerStyle: 'unknown' }, target as never);
    assert.equal(target.values.get('--accent-hue'), '29.2');
    assert.equal(target.values.get('--corner-lg'), '24px');
    applyBrandTheme({ accentColor: null, cornerStyle: 'round', logoHeight: 36 }, target as never);
    assert.equal(target.values.get('--logo-height'), '2.25rem');
    applyBrandTheme({ accentColor: null, cornerStyle: 'round', logoHeight: 400 }, target as never);
    assert.equal(target.values.get('--logo-height'), '2.25rem');
    // Unset returns each logo to its own size, also over a value the server rendered.
    applyBrandTheme({ accentColor: null, cornerStyle: 'round', logoHeight: null }, target as never);
    assert.equal(target.values.get('--logo-height'), 'initial');
    assert.equal(target.values.get('--accent-hue'), '265');
    assert.equal(target.values.get('--accent-chroma'), '1');
});
