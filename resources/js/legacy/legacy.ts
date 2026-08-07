import {getAuthenticatedConnection, getConnection, getConnectionWithUserInfo} from '$lib/kernel/client/helpers.js';
import {__} from '$lib/kernel/localization/helpers.js';
import {oldUiBridge} from '$lib/legacy/OldUiBridge.svelte.js';
import {applyMigrations} from '$lib/kernel/migrations/helpers.js';
import {getFileIconSvg} from '$lib/utils/fileIconSvg.js';
import {oldUiMessageHistory} from '$lib/legacy/OldUiMessageHistory.svelte.js';
import {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import {dependencyLoader} from '$lib/legacy/dependencies.js';
import {buildStorageFileUrl} from '$lib/utils/storageFileProxy.js';
import {getConfig} from '$lib/kernel/config/helpers.js';
import type {WellKnownSystemModelType} from '$plugins/core/schemas/resources/system-models.schema.js';
import type {WellKnownSystemPromptType} from '$plugins/core/schemas/resources/system-prompts.schema.js';
import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {KeychainStore} from '$plugins/core/stores/KeychainStore.svelte.js';

// Augment the global Window interface to include our globals, so that they can be accessed without TypeScript errors.
// WARNING: This is only here for legacy support! Do not use global variables in new code!
declare global {
    interface Window {
        hawkiIsBooting: boolean;
        hawkiEarlyWaitUntilBootstrapQueue: Array<(bootstrapper: Bootstrapper) => Promise<void>>;
        hawkiEarlyWaitUntilReadyQueue: Array<() => Promise<void>>;
        hawkiIsReady: boolean;
        hawkiDependencyLoader: typeof dependencyLoader;
        waitUntilBootstrap: (cb: (bootstrapper: Bootstrapper) => Promise<void> | void) => void;
        waitUntilReady: (cb: () => Promise<void> | void) => void;
        oldUiBridge: typeof oldUiBridge;
        oldUiMessageHistory: typeof oldUiMessageHistory;
        getConnection: typeof getConnection;
        getAuthenticatedConnection: typeof getAuthenticatedConnection;
        getConnectionWithUserInfo: typeof getConnectionWithUserInfo;
        getConfig: typeof getConfig;
        applyMigrations: typeof applyMigrations;
        __: typeof __;
        buildStorageFileUrl: typeof buildStorageFileUrl;
        userKeychain: KeychainStore;
        getAiModels: () => AiModel[];
        getAiModel: (id: string | number) => AiModel | null;
        getSystemModel: (modelType: WellKnownSystemModelType | string) => AiModel | null;
        getSystemPrompt: (promptName: WellKnownSystemPromptType) => string | null;
        getFileIconSvg: typeof getFileIconSvg;
    }
}

/**
 * @deprecated Will be removed as soon as all the old js has been refactored
 */
export function provideLegacyGlobals() {
    if (window.hawkiIsBooting) {
        throw new Error('Hawki is already booting. This may indicate that the bootstrap script has been included multiple times.');
    }
    window.hawkiIsBooting = true;

    function getAiModelStore() {
        return getHawkiApp().stores.get('ai-models');
    }

    // Propagate some important functions and objects to the global scope, so the legacy code can access them.
    window.getConnection = getConnection;
    window.getAuthenticatedConnection = getAuthenticatedConnection;
    window.getConnectionWithUserInfo = getConnectionWithUserInfo;
    window.getConfig = getConfig;
    window.__ = __;
    window.applyMigrations = applyMigrations;
    window.oldUiBridge = oldUiBridge;
    window.oldUiMessageHistory = oldUiMessageHistory;
    window.buildStorageFileUrl = buildStorageFileUrl;
    window.hawkiDependencyLoader = dependencyLoader;
    window.getAiModels = () => getAiModelStore().models;
    window.getAiModel = (id: string | number) => getAiModelStore().getOneById(id);
    window.getSystemModel = (modelType: WellKnownSystemModelType | string) => {
        return getAiModelStore().getSystemModelByType(modelType);
    };
    window.getSystemPrompt = (promptType: WellKnownSystemPromptType) => {
        return getHawkiApp().stores.get('system-prompts').promptsByType.get(promptType)?.prompt ?? null;
    };
    window.getFileIconSvg = getFileIconSvg;

    // Assign userKeychain property using a getter to ensure it always returns the latest instance of KeychainStore
    Object.defineProperty(window, 'userKeychain', {
        get: () => {
            console.warn(getHawkiApp().stores, 'Äapp');
            return getHawkiApp().stores.get('keychain');
        }
    });
}

/**
 * @deprecated Will be removed as soon as all the old js has been refactored
 */
export async function runLegacyWaitUntilBootstrapQueue(bootstrapper: Bootstrapper) {
    if (Array.isArray(window.hawkiEarlyWaitUntilBootstrapQueue)) {
        for (const cb of window.hawkiEarlyWaitUntilBootstrapQueue) {
            await cb(bootstrapper);
        }
    }
}

/**
 * @deprecated Will be removed as soon as all the old js has been refactored
 */
export async function runLegacyWaitUntilReadyQueue() {
    window.hawkiIsReady = true;
    if (Array.isArray(window.hawkiEarlyWaitUntilReadyQueue)) {
        for (const cb of window.hawkiEarlyWaitUntilReadyQueue) {
            await cb();
        }
    }
}

let hawkiAppInstance: HawkiApp | null = null;

/**
 * @deprecated Will be removed as soon as all the old js has been refactored
 */
export function setHawkiApp(app: HawkiApp) {
    if (hawkiAppInstance) {
        throw new Error('HawkiApp instance has already been set.');
    }
    hawkiAppInstance = app;
    return app;
}

/**
 * @deprecated Will be removed as soon as all the old js has been refactored
 */
export function getHawkiApp(): HawkiApp {
    if (!hawkiAppInstance) {
        throw new Error('HawkiApp instance has not been set yet.');
    }
    return hawkiAppInstance;
}
