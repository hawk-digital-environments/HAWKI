import {authRouter} from './routerFixture.js';
const router = authRouter();
import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {assignAuthPage, sanitizeNext, authPageUrl, currentNext} from '../../../resources/js/kernel/auth/navigation.js';

test('next accepts SPA paths and preserves query and fragment', () => {
    assert.equal(sanitizeNext(router, '%2Fnew%2Fchat%3Fq%3Dhello%23message'), '/new/chat?q=hello#message');
    const url = new URL(authPageUrl(router, 'handshake', '/new/chat?q=hello#message'), 'https://hawki.test');
    assert.equal(url.searchParams.get('next'), '/new/chat?q=hello#message');
});

test('next rejects external, escaped, control and normalized escape paths', () => {
    for (const value of [null, '', 'https://evil.test/new/chat', '//evil.test/new/chat', '/login', '/new', '/new/../login', '/new/%2e%2e/login', '/new/%252e%252e/login', '/new/\\evil', '/new/%255cevil', '/new/a%0ab', '/new/a\u007fb', '%zz']) {
        assert.equal(sanitizeNext(router, value), undefined, String(value));
    }
    assert.equal(authPageUrl(router, 'login', '//evil.test'), '/new/auth/login');
});

test('auth redirects retain the original next without nesting auth pages or looping', () => {
    const destinations: string[] = [];
    Object.assign(globalThis, {window: {location: {
        pathname: '/new/auth/handshake',
        search: '?next=%2Fnew%2Fchat%3Fthread%3D1',
        hash: '',
        assign: (url: string) => destinations.push(url)
    }}});
    assert.equal(currentNext(router), '/new/chat?thread=1');
    assert.equal(assignAuthPage(router, 'login'), true);
    assert.deepEqual(destinations, ['/new/auth/login?next=%2Fnew%2Fchat%3Fthread%3D1']);

    window.location.pathname = '/new/auth/login';
    window.location.search = '?next=%2Fnew%2Fchat%3Fthread%3D1';
    assert.equal(assignAuthPage(router, 'login'), false);
    assert.equal(destinations.length, 1);
});

test('auth navigation follows a relocated router base and named routes', () => {
    const relocated = authRouter('/workspace');
    assert.equal(authPageUrl(relocated, 'login', '/workspace/chat'), '/workspace/auth/login?next=%2Fworkspace%2Fchat');
    assert.equal(sanitizeNext(relocated, '/new/chat'), undefined);
    assert.equal(sanitizeNext(relocated, '/workspace/../outside'), undefined);
    assert.equal(sanitizeNext(relocated, '/workspace/chat?q=1#last'), '/workspace/chat?q=1#last');
    assert.equal(authPageUrl(relocated, 'login', undefined, 'untrusted'), '/workspace/auth/login');
    assert.equal(authPageUrl(relocated, 'login', undefined, 'session_expired'), '/workspace/auth/login?reason=session_expired');
});
