import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {isLoginVideoEnabled, pickLoginBackground, setLoginVideoEnabled, type LoginBackground} from '../../../resources/js/plugins/auth/pages/loginBackground.js';

function storage() {
    const values = new Map<string, string>();
    return {getItem: (key: string) => values.get(key) ?? null, setItem: (key: string, value: string) => {values.set(key, value);}, removeItem: (key: string) => {values.delete(key);}};
}
const entry = (url: string): LoginBackground => ({url, kind: url.endsWith('.mp4') ? 'video' : 'image', credit: 'Artist', link: null});

test('backgrounds take turns across visits, remembered in the browser', () => {
    const saved = storage();
    saved.setItem('hawki.auth.background', '0');
    const list = [entry('/one.mp4'), entry('/two.jpg'), entry('/three.mp4')];
    assert.deepEqual([1, 2, 3, 4].map(() => pickLoginBackground(list, saved)?.url), ['/two.jpg', '/three.mp4', '/one.mp4', '/two.jpg']);
});

test('without backgrounds the canvas shows the logo', () => {
    assert.equal(pickLoginBackground([], storage()), null);
});

test('unavailable browser storage does not prevent a background', () => {
    const unavailable = {getItem: () => {throw new Error('blocked');}, setItem: () => {}, removeItem: () => {}};
    assert.equal(pickLoginBackground([entry('/a.jpg')], unavailable)?.credit, 'Artist');
});

test('login video is enabled by default and only stored zero disables it', () => {
    const saved = storage();
    assert.equal(isLoginVideoEnabled(saved), true);
    saved.setItem('hawki.auth.backgroundVideo', '0');
    assert.equal(isLoginVideoEnabled(saved), false);
    setLoginVideoEnabled(saved, true);
    assert.equal(isLoginVideoEnabled(saved), true);
});
