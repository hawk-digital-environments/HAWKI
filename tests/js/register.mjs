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
const testsRoot = path.join(projectRoot, 'tests/js');

/** Whether `candidate` is `root` itself or somewhere below it. */
/**
 * @param {string} root
 * @param {string} candidate
 */
function isWithin(root, candidate) {
    return candidate === root || candidate.startsWith(`${root}${path.sep}`);
}

/** @param {string} specifier */
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
/** @param {string} target */
function sourceFile(target) {
    const candidates = target.endsWith('.js')
        ? [target, `${target.slice(0, -3)}.ts`]
        : [`${target}.ts`, path.join(target, 'index.ts'), target];
    return candidates.find(candidate => existsSync(candidate) && statSync(candidate).isFile()) ?? null;
}

registerHooks({
    resolve(specifier, context, nextResolve) {
        if (specifier.endsWith('?worker&url')) {
            return {url: 'data:text/javascript,export default "/search.worker.js";', shortCircuit: true};
        }
        let target = aliasedPath(specifier);
        let ownSource = target !== null;
        if (target === null && (specifier.startsWith('./') || specifier.startsWith('../'))) {
            const parent = context.parentURL?.startsWith('file:')
                ? path.dirname(fileURLToPath(context.parentURL))
                : projectRoot;
            target = path.resolve(parent, specifier);
            // Only the project's own sources are ours to redirect and load as
            // ESM; a relative import inside a dependency (a `.cjs`, a `.json`)
            // must keep Node's own resolution.
            ownSource = isWithin(libRoot, target) || isWithin(testsRoot, target);
        }
        if (target === null || !ownSource) {
            return nextResolve(specifier, context);
        }
        const resolved = sourceFile(target);
        return resolved === null
            ? nextResolve(specifier, context)
            : {url: pathToFileURL(resolved).href, format: 'module', shortCircuit: true};
    },

    load(url, context, nextLoad) {
        // Dependencies ship uncompiled rune modules too. Keep dev comparisons
        // enabled so regression tests can catch proxy identity warnings.
        if (url.startsWith('file:') && url.endsWith('.svelte.js')) {
            const file = fileURLToPath(url);
            const source = compileModule(readFileSync(file, 'utf8'), {
                filename: file, generate: 'client', dev: true
            }).js.code;
            return {format: 'module', source, shortCircuit: true};
        }
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
