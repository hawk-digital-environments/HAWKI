import UniversalRouter from 'universal-router';
import generateUrls from 'universal-router/generateUrls';
import type {AuthRouter} from '../../../resources/js/kernel/auth/navigation.js';

export function authRouter(basePath = '/new'): AuthRouter {
    const router = new UniversalRouter([
        ...['login', 'register', 'handshake', 'inconsistent'].map(page => ({path: `/auth/${page}`, name: `auth.${page}`})),
        {path: '/chat', name: 'chat.index'}
    ], {baseUrl: basePath});
    const urls = generateUrls(router, {stringifyQueryParams: params => new URLSearchParams(params as Record<string, string>).toString()});
    return {getPath: (name, params) => name === '/' ? `${basePath}/` : urls(name, params)};
}
