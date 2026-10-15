import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { accentTokens, applyBrandTheme, fontFaces, isFontFile, oklch } from '../../../resources/js/plugins/admin/brandTheme.js';

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

test('status colors take OKLCH values, return to the built-in color when emptied and ignore half-typed ones', () => {
    assert.equal(oklch(' OKLCH( 40%  0.2 10 ) '), 'oklch(40% 0.2 10)');
    for (const value of ['oklch(101% 0.2 10)', 'oklch(50% 0.5 10)', 'oklch(50% 0.2 361)', 'oklch(50% 0.2 10 / 0.5)', 'red', '', null])
        assert.equal(oklch(value), null);
    const target = root();
    applyBrandTheme({ statusColors: { error: 'oklch(40% 0.2 10)', info: 'oklch(40%' } }, target as never);
    assert.equal(target.values.get('--color-error'), 'oklch(40% 0.2 10)');
    assert.equal(target.values.has('--color-info'), false);
    assert.equal(target.values.get('--color-success'), 'oklch(50% 0.17 145)');
    applyBrandTheme({ statusColors: { error: '' } }, target as never);
    assert.equal(target.values.get('--color-error'), 'oklch(52% 0.21 25)');
});

test('the preview applies a color, keeps the accent while one is half-typed and returns to the defaults', () => {
    const target = root();
    applyBrandTheme({ accentColor: '#ff0000', cornerStyle: 'square' }, target as never);
    assert.equal(target.values.get('--accent-hue'), '29.2');
    assert.equal(target.values.get('--corner-lg'), '0');
    assert.equal(target.values.get('--corner-control'), '0px');
    assert.equal(target.values.get('--corner-control-sm'), '2px');
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

test('font faces follow the uploaded weights (BrandThemeTest.php pins the same rules)', () => {
    const regular = 'a'.repeat(64) + '.woff2';
    const bold = 'b'.repeat(64) + '.otf';
    const variable = 'c'.repeat(64) + '.var.ttf';
    const url = (file: string) => '/fonts/' + file;
    const face = (file: string, format: string, weight: string) =>
        `@font-face{font-family:"HAWKI Brand";src:url("/fonts/${file}") format("${format}");font-weight:${weight};font-display:swap}`;
    assert.equal(isFontFile('x");}body{display:none'), false);
    assert.equal(isFontFile('f'.repeat(64) + '.ttc'), false);
    assert.equal(fontFaces({}, url), '');
    assert.equal(fontFaces({600: bold}, url), '');
    assert.equal(fontFaces({400: regular}, url, 'HAWKI Brand'), face(regular, 'woff2', '400'));
    assert.equal(fontFaces({400: regular, 600: bold}, url, 'HAWKI Brand'), face(regular, 'woff2', '400') + face(bold, 'opentype', '600'));
    assert.equal(fontFaces({400: variable, 600: bold}, url, 'HAWKI Brand'), face(variable, 'truetype', '100 900'));
    const italic = 'e'.repeat(64) + '.woff2';
    assert.equal(fontFaces({}, url, 'HAWKI Brand', {400: italic}), '');
    assert.equal(
        fontFaces({400: regular}, url, 'HAWKI Brand', {400: italic}),
        face(regular, 'woff2', '400') + face(italic, 'woff2', '400').replace(';font-display', ';font-style:italic;font-display')
    );
});
