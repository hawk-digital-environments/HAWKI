import {Command} from 'commander';
import type {Paths} from './Paths.ts';
import type {Platform} from './Platform.ts';
import type {EnvFile} from './env/EnvFile.ts';
import type {EventBus} from './EventBus.ts';
import type {PackageInfo} from './PackageInfo.ts';
import type {CommonUi} from './CommonUi.ts';

export interface Context {
    readonly ui: CommonUi;
    readonly pkg: PackageInfo;
    readonly paths: Paths;
    readonly events: EventBus;
    readonly env: EnvFile;
    readonly program: Command;
    readonly platform: Platform;
}

const targetProp = Symbol('targetProp');

export function createContext(events: EventBus, ui: CommonUi): Context {
    return new Proxy({
        events,
        ui,
        program: new Command()
    } as Context, {
        get(target, prop) {
            if (prop === targetProp) {
                return target;
            }
            if (prop in target) {
                return target[prop];
            }
            throw new Error(`Property ${String(prop)} is not available in the context. Maybe you are to early?`);
        }
    });
}

export function extendContext(context: Context, key: keyof Context, value: object | (() => object)): Context {
    const target = (context as any)[targetProp];
    if (typeof value === 'function') {
        let _value: any;
        Object.defineProperty(target, key, {
            get() {
                return _value ??= value();
            },
            enumerable: true,
            configurable: true
        });
    } else {
        target[key] = value;
    }
    return context;
}
