import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {pickLoginBackground} from '../../../resources/js/plugins/core/pages/auth/loginBackground.js';

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
