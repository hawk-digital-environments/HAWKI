import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {isLoginVideoEnabled, pickLoginBackground, setLoginVideoEnabled} from '../../../resources/js/plugins/auth/pages/loginBackground.js';

function storage() {
    const values = new Map<string, string>();
    return {getItem: (key: string) => values.get(key) ?? null, setItem: (key: string, value: string) => {values.set(key, value);}, removeItem: (key: string) => {values.delete(key);}};
}
const entry = (file: string) => ({file, creator: 'Artist', link: 'https://artist.test'});

test('background selection rotates separately per theme through injected storage and HTTP', async () => {
    const saved = storage();
    saved.setItem('hawki.auth.background.light', '0');
    const load = async (url: string) => {
        assert.equal(url, 'https://hawki.test/bg_videos/bg_videos.json');
        return {lightmode: [entry('one.mp4'), entry('two.mp4')], darkmode: [entry('dark.mp4')]};
    };
    const dependencies = {load, storage: saved};
    assert.equal((await pickLoginBackground('https://hawki.test/', 'light', dependencies))?.src, 'https://hawki.test/bg_videos/two.mp4');
    assert.equal((await pickLoginBackground('https://hawki.test', 'light', dependencies))?.src, 'https://hawki.test/bg_videos/one.mp4');
    assert.equal((await pickLoginBackground('https://hawki.test', 'dark', dependencies))?.src, 'https://hawki.test/bg_videos/dark.mp4');
});

test('missing, malformed and unsafe indexes fall back without throwing', async () => {
    for (const index of [null, {}, {lightmode: 'invalid'}, {lightmode: [{}]}, {lightmode: [{...entry('a.mp4'), link: 'javascript:alert(1)'}]}]) {
        assert.equal(await pickLoginBackground('', 'light', {load: async () => index, storage: storage()}), null);
    }
    assert.equal(await pickLoginBackground('', 'light', {load: async () => {throw new Error('offline');}, storage: storage()}), null);
});

test('unavailable browser storage does not prevent a valid background', async () => {
    const unavailable = {getItem: () => {throw new Error('blocked');}, setItem: () => {}, removeItem: () => {}};
    assert.equal((await pickLoginBackground('', 'light', {load: async () => ({lightmode: [entry('a.mp4')]}), storage: unavailable}))?.creator, 'Artist');
});

test('login video is enabled by default and only stored zero disables it', () => {
    const saved = storage();
    assert.equal(isLoginVideoEnabled(saved), true);
    saved.setItem('hawki.auth.backgroundVideo', '0');
    assert.equal(isLoginVideoEnabled(saved), false);
    for (const value of ['1', 'garbage']) {saved.setItem('hawki.auth.backgroundVideo', value); assert.equal(isLoginVideoEnabled(saved), true);}
});

test('login video preference stores disabled and removes enabled', () => {
    const saved = storage();
    setLoginVideoEnabled(saved, false);
    assert.equal(saved.getItem('hawki.auth.backgroundVideo'), '0');
    assert.equal(isLoginVideoEnabled(saved), false);
    setLoginVideoEnabled(saved, true);
    assert.equal(saved.getItem('hawki.auth.backgroundVideo'), null);
    assert.equal(isLoginVideoEnabled(saved), true);
});

test('login video preference tolerates unavailable storage', () => {
    const unavailable = {getItem: () => {throw new Error('blocked');}, setItem: () => {throw new Error('blocked');}, removeItem: () => {throw new Error('blocked');}};
    assert.equal(isLoginVideoEnabled(unavailable), true);
    assert.doesNotThrow(() => setLoginVideoEnabled(unavailable, false));
    assert.doesNotThrow(() => setLoginVideoEnabled(unavailable, true));
});
