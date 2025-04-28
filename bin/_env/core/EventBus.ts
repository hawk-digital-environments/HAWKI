import type {EnvFile} from './env/EnvFile.ts';
import type {EnvFileDefinition} from './env/EnvFileMigrator.ts';
import type {Command} from 'commander';

export interface AsyncEventTypes {
    /**
     * Executed if there is no .env file, right before we try to copy the template file.
     */
    'env:initialize:before': { envFilePath: string, templatePath: string };
    /**
     * Executed when the env file migration is started.
     * It allows custom code to define variables for the migration.
     */
    'env:define': { definition: EnvFileDefinition, envFile: EnvFile };
    /**
     * Executed when the program is being defined.
     * This allows custom code to add commands to the program.
     */
    'commands:define': { program: Command };
    /**
     * Executed when an error occurs.
     * This hook is executed before the error is printed to the console.
     */
    'error:before': { error: Error };
    /**
     * Executed when an error occurs.
     * This hook is executed after the error was printed to the console.
     */
    'error:after': { error: Error };
}

interface SyncEventTypes {
    'ui:filter:helpHeader': { value: string };
    'ui:filter:helpDescription': { value: string };
    'ui:filter:errorHeader': { value: string };
}

export class EventBus {
    private readonly _async: Map<keyof AsyncEventTypes, Set<(arg: AsyncEventTypes[keyof AsyncEventTypes]) => Promise<void>>> = new Map();
    private readonly _sync: Map<keyof SyncEventTypes, Set<(arg: SyncEventTypes[keyof SyncEventTypes]) => void>> = new Map();

    public async trigger<E extends keyof AsyncEventTypes>(event: E, arg: AsyncEventTypes[E] = undefined): Promise<AsyncEventTypes[E]> {
        const callbacks = this._async.get(event);
        if (callbacks) {
            for (const callback of callbacks) {
                await callback(arg);
            }
        }
        return arg;
    }

    public triggerSync<E extends keyof SyncEventTypes>(event: E, arg: SyncEventTypes[E] = undefined): SyncEventTypes[E] {
        const callbacks = this._sync.get(event);
        if (callbacks) {
            for (const callback of callbacks) {
                callback(arg);
            }
        }
        return arg;
    }

    public on<E extends keyof AsyncEventTypes>(event: E, callback: (arg: AsyncEventTypes[E]) => Promise<void>): this {
        if (!this._async.has(event)) {
            this._async.set(event, new Set());
        }
        this._async.get(event)!.add(callback);
        return this;
    }

    public onSync<E extends keyof SyncEventTypes>(event: E, callback: (arg: SyncEventTypes[E]) => void): this {
        if (!this._sync.has(event)) {
            this._sync.set(event, new Set());
        }
        this._sync.get(event)!.add(callback);
        return this;
    }
}
