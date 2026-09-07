/**
 * Test-only module hooks: resolve the frontend's `$lib`/`$plugins` aliases and
 * its `.js` import specifiers onto the `.ts` sources, transpile TypeScript with
 * esbuild and compile `.svelte.ts` runes with the Svelte module compiler.
 *
 * This mirrors what Vite does in the browser build, so the tests import the
 * production files unchanged instead of a parallel copy of them.
 */
import {registerHooks} from 'node:module';
import {existsSync, readFileSync, statSync} from 'node:fs';
import {fileURLToPath, pathToFileURL} from 'node:url';
import path from 'node:path';
import {transformSync} from 'esbuild';
import {compileModule} from 'svelte/compiler';

const projectRoot = path.resolve(import.meta.dirname, '../..');
const libRoot = path.join(projectRoot, 'resources/js');

function aliasedPath(specifier) {
    if (specifier === '$lib') {
        return libRoot;
    }
    if (specifier.startsWith('$lib/')) {
        return path.join(libRoot, specifier.slice('$lib/'.length));
    }
    if (specifier.startsWith('$plugins/')) {
        return path.join(libRoot, 'plugins', specifier.slice('$plugins/'.length));
    }
    return null;
}

/** The `.ts` file a `.js` specifier (or an extensionless one) actually refers to. */
function sourceFile(target) {
    const candidates = target.endsWith('.js')
        ? [target, `${target.slice(0, -3)}.ts`]
        : [`${target}.ts`, path.join(target, 'index.ts'), target];
    return candidates.find(candidate => existsSync(candidate) && statSync(candidate).isFile()) ?? null;
}

registerHooks({
    resolve(specifier, context, nextResolve) {
        if (specifier.includes('?worker&url')) {
            return {url: 'data:text/javascript,export default "/search.worker.js";', shortCircuit: true};
        }
        let target = aliasedPath(specifier);
        if (target === null && (specifier.startsWith('./') || specifier.startsWith('../'))) {
            const parent = context.parentURL?.startsWith('file:')
                ? path.dirname(fileURLToPath(context.parentURL))
                : projectRoot;
            target = path.resolve(parent, specifier);
        }
        if (target === null) {
            return nextResolve(specifier, context);
        }
        const resolved = sourceFile(target);
        return resolved === null
            ? nextResolve(specifier, context)
            : {url: pathToFileURL(resolved).href, format: 'module', shortCircuit: true};
    },

    load(url, context, nextLoad) {
        if (!url.startsWith('file:') || !url.endsWith('.ts')) {
            return nextLoad(url, context);
        }
        const file = fileURLToPath(url);
        let source = transformSync(readFileSync(file, 'utf8'), {
            loader: 'ts',
            format: 'esm',
            target: 'es2022',
            sourcefile: file
        }).code;
        if (file.endsWith('.svelte.ts')) {
            source = compileModule(source, {filename: file, generate: 'client'}).js.code;
        }
        return {format: 'module', source, shortCircuit: true};
    }
});
