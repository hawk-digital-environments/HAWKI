import {test} from 'node:test';
import assert from 'node:assert/strict';
import {cssDeclaration, overlayContrastMode, perceptualLightness} from '$lib/utils/color.js';

test('perceptualLightness: black is 0, white is 1', () => {
    assert.equal(perceptualLightness(0, 0, 0), 0);
    // The coefficients sum to 1 only within float precision (1.0000001).
    assert.ok(Math.abs(perceptualLightness(255, 255, 255) - 1) < 1e-6);
});

test('perceptualLightness: saturated red reads darker than gamma luma', () => {
    // The curve compresses pure red below what a sqrt gamma-luma would give,
    // which overestimates the brightness of saturated reds and blues.
    const naiveGammaLuma = Math.sqrt(0.2126729);
    assert.ok(perceptualLightness(255, 0, 0) < naiveGammaLuma);
});

test('overlayContrastMode: rgb() covers split around the threshold', () => {
    assert.equal(overlayContrastMode('rgb(0, 97, 57)'), 'dark');
    assert.equal(overlayContrastMode('rgb(0 97 57)'), 'dark');
    assert.equal(overlayContrastMode('rgb(255, 255, 255)'), 'light');
    assert.equal(overlayContrastMode('rgba(250, 235, 215, 0.9)'), 'light');
});

test('overlayContrastMode: hsl() covers (the avatar presets\' format)', () => {
    // The "Wald" preset: hsl(155 100% 19%) — a deep green.
    assert.equal(overlayContrastMode('hsl(155 100% 19%)'), 'dark');
    // The "Blush" preset: hsl(330 85% 90%) — a pale rosé.
    assert.equal(overlayContrastMode('hsl(330 85% 90%)'), 'light');
    // hsl() with commas and a deg hue, as browsers serialize it.
    assert.equal(overlayContrastMode('hsl(228deg, 80%, 52%)'), 'dark');
});

test('overlayContrastMode: null/fallback for absent or unparseable input', () => {
    assert.equal(overlayContrastMode(null), null);
    assert.equal(overlayContrastMode(undefined), null);
    // Not rgb()/hsl(), and no DOM in this runner — the fallback applies.
    assert.equal(overlayContrastMode('oklch(80% 0.1 200)'), null);
    assert.equal(overlayContrastMode('nope', {fallback: 'light'}), 'light');
});

test('overlayContrastMode: threshold moves the split', () => {
    // A mid gray: dark at the default, light under a stricter threshold.
    assert.equal(overlayContrastMode('rgb(150, 150, 150)'), 'dark');
    assert.equal(overlayContrastMode('rgb(150, 150, 150)', {threshold: 0.4}), 'light');
});

test('cssDeclaration: reads one declaration out of an iconCss string', () => {
    const iconCss = 'background: hsl(155 100% 19%); color: hsl(72 90% 62%)';
    assert.equal(cssDeclaration(iconCss, 'background'), 'hsl(155 100% 19%)');
    assert.equal(cssDeclaration(iconCss, 'color'), 'hsl(72 90% 62%)');
    assert.equal(cssDeclaration(iconCss, 'border'), null);
    // A leading declaration without a preceding semicolon still matches.
    assert.equal(cssDeclaration('color: red', 'color'), 'red');
});
