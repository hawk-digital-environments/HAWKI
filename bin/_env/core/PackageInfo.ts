import type {Paths} from './Paths.ts';
import fs from 'node:fs';
import path from 'node:path';

export interface PackageInfo {
    name: string,
    version: string,
    description: string
}

export function createPackageJson(paths: Paths): PackageInfo {
    const content = fs.readFileSync(path.resolve(paths.envDir, 'package.json'));
    const pkg: Record<string, any> = JSON.parse(content.toString('utf-8'));

    return {
        name: (pkg.name ?? 'bin/env').replace(/^@/, ''),
        version: pkg.version ?? '1.0.0',
        description: pkg.description ?? ''
    };
}
