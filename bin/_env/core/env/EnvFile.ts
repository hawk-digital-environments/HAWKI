import {type EnvFileState, loadEnvFileState} from './EnvFileState.ts';
import {EnvFileLine} from './EnvFileLine.ts';
import {ensureEnvFileExists, envFileHashChanged} from './util.ts';
import {EnvFileMigrator} from './EnvFileMigrator.ts';
import type {Context} from '../Context.ts';

export class EnvFile {
    private readonly _state: EnvFileState;

    public constructor(state: EnvFileState) {
        this._state = state;
    }

    public get(key: string, fallback?: string): string | undefined {
        return this._state.getFirstLineForKey(key)?.value || fallback;
    }

    public getRequired(key: string, fallback?: string): string {
        const value = this.get(key, fallback);
        if (value !== undefined) {
            return value;
        }
        throw new Error(`Required env variable ${key} not found`);
    }

    /**
     * Get the value of an env variable from the local env file or from the global env variables
     * Use `get` to get the value from the local env file only
     * @param key
     * @param fallback
     */
    public getGlobal(key: string, fallback?: string): string | undefined {
        const localValue = this.get(key);
        if (localValue !== undefined) {
            return localValue;
        }

        if (process.env[key] !== undefined) {
            return process.env[key] as string;
        }

        if (fallback !== undefined) {
            return fallback;
        }

        return undefined;
    }

    /**
     * The same as `getGlobal`, but throws an error if the value is not found (and no fallback is provided)
     * @param key
     * @param fallback
     */
    public getGlobalRequired(key: string, fallback?: string): string {
        const value = this.getGlobal(key, fallback);
        if (value !== undefined) {
            return value;
        }
        throw new Error(`Required env variable ${key} not found`);
    }

    public has(key: string): boolean {
        return this._state.getFirstLineForKey(key) !== undefined;
    }

    public isEmpty(key: string): boolean {
        const line = this._state.getFirstLineForKey(key);
        return line === undefined || line.value === undefined || line.value === '';
    }

    public set(key: string, value: string): this {
        const line = this._state.getFirstLineForKey(key);
        if (line) {
            line.value = value;
        } else {
            this._state.addLine(new EnvFileLine(key + '=' + value));
        }
        return this;
    }

    public get state(): EnvFileState {
        return this._state;
    }

    public write(): void {
        this._state.write();
    }
}

export async function createEnvFile({events, paths}: Context): Promise<EnvFile> {
    await ensureEnvFileExists(events, paths);

    const envFile = new EnvFile(loadEnvFileState(paths.envFilePath));

    if (envFileHashChanged(paths)) {
        await (new EnvFileMigrator(events, paths)).migrate(envFile);
    }

    return envFile;
}
