import {getAuthenticatedConnection, getConnection, getConnectionWithUserInfo, getRegisteringUserConnection, loadConnection} from '$lib/data/connection/connection.js';
import {autoRegisterConfigSchemas, getConfig, loadConfig} from '$lib/data/config/config.js';
import {initializeEcho} from '$lib/echo.js';
import {autoRegisterResourceSchemas} from '$lib/data/resources/resourceRegistry.js';
import {__, hasTranslation, loadTranslationLabels} from '$lib/utils/translator.js';
import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';
import {buildStorageFileUrl} from '$lib/utils/storageFileProxy.js';
import {applyMigrations, autoRegisterMigrations} from '$lib/data/migrations/migrator.js';
import {aiModelStore, loadAiModels} from '$lib/stores/AiModelStore.svelte.js';
import {registerSvelteSnippetLoader} from '$lib/svelteSnippetLoader.js';
import {loadSystemPrompts, systemPromptStore} from '$lib/stores/SystemPromptStore.svelte.js';
import {keychainStore, KeychainStore} from '$lib/stores/KeychainStore.svelte.js';
import {loadAiToolsAndCapabilities} from '$lib/stores/AiToolStore.svelte.js';
import {encryptHybrid} from '$lib/encryption/hybrid.js';
import type {WellKnownSystemModelType} from '$lib/schemas/resources/system-models.schema.js';
import type {AiModel} from '$lib/schemas/resources/ai-models.schema.js';
import type {WellKnownSystemPromptType} from '$lib/schemas/resources/system-prompts.schema.js';
import {getFileIconSvg} from '$lib/utils/fileIconSvg.js';
import {oldUiMessageHistory} from '$lib/oldUi/OldUiMessageHistory.svelte.js';
import {bootstrapper, Bootstrapper} from '$lib/utils/Bootstrapper.js';

// Augment the global Window interface to include our globals, so that they can be accessed without TypeScript errors.
// WARNING: This is only here for legacy support! Do not use global variables in new code!
declare global {
    interface Window {
        hawkiEarlyWaitUntilBootstrapQueue: Array<(bootstrapper: Bootstrapper) => Promise<void>>;
        hawkiEarlyWaitUntilReadyQueue: Array<() => Promise<void>>;
        hawkiBootstrap: Bootstrapper;
        hawkiIsReady: boolean;
        waitUntilBootstrap: (cb: (bootstrapper: Bootstrapper) => Promise<void> | void) => void;
        waitUntilReady: (cb: () => Promise<void> | void) => void;
        getConnection: typeof getConnection;
        getAuthenticatedConnection: typeof getAuthenticatedConnection;
        getRegisteringUserConnection: typeof getRegisteringUserConnection;
        getConnectionWithUserInfo: typeof getConnectionWithUserInfo;
        getConfig: typeof getConfig;
        applyMigrations: typeof applyMigrations;
        __: typeof __;
        hasTranslation: typeof hasTranslation;
        oldUiBridge: typeof oldUiBridge;
        oldUiMessageHistory: typeof oldUiMessageHistory;
        buildStorageFileUrl: typeof buildStorageFileUrl;
        userKeychain: KeychainStore;
        getAiModels: () => AiModel[];
        getAiModel: (id: string | number) => AiModel | null;
        getSystemModel: (modelType: WellKnownSystemModelType | string) => AiModel | null;
        getSystemPrompt: (promptName: WellKnownSystemPromptType) => string | null;
        getFileIconSvg: typeof getFileIconSvg;
        hawkiCrypto: {
            encryptHybrid: typeof encryptHybrid;
        };
    }
}

export function run() {
    autoRegisterResourceSchemas();
    autoRegisterConfigSchemas();
    autoRegisterMigrations();

    // Propagate some important functions and objects to the global scope, so the legacy code can access them.
    window.getConnection = getConnection;
    window.getAuthenticatedConnection = getAuthenticatedConnection;
    window.getRegisteringUserConnection = getRegisteringUserConnection;
    window.getConnectionWithUserInfo = getConnectionWithUserInfo;
    window.getConfig = getConfig;
    window.__ = __;
    window.hasTranslation = hasTranslation;
    window.buildStorageFileUrl = buildStorageFileUrl;
    window.applyMigrations = applyMigrations;
    window.userKeychain = keychainStore;
    window.oldUiBridge = oldUiBridge;
    window.oldUiMessageHistory = oldUiMessageHistory;
    window.hawkiCrypto = {
        encryptHybrid
    };
    window.getAiModels = () => aiModelStore.models;
    window.getAiModel = (id: string | number) => aiModelStore.getOneById(id);
    window.getSystemModel = (modelType: WellKnownSystemModelType | string) => {
        return aiModelStore.getSystemModelByType(modelType);
    };
    window.getSystemPrompt = (promptType: WellKnownSystemPromptType) => {
        return systemPromptStore.getPromptByType(promptType)?.prompt ?? null;
    };
    window.getFileIconSvg = getFileIconSvg;

    // Before the bootstrap, we must load the connection and config, since everything else depends on them.
    // They are loaded simultaneously, but before the rest of the bootstrap steps, to minimize the time spent waiting for them.
    bootstrapper.onPreparationStage(loadConnection);
    bootstrapper.onPreparationStage(loadConfig);

    // Main bootstrap, these can be run in parallel, but they must be run after the connection and config are loaded.
    bootstrapper.onMainStage(loadTranslationLabels);
    bootstrapper.onMainStage(loadAiModels);
    bootstrapper.onMainStage(loadAiToolsAndCapabilities);
    bootstrapper.onMainStage(loadSystemPrompts);

    // Next, we initialize some globals, such as the Echo instance for real-time events.
    bootstrapper.onLateStage(initializeEcho);

    // As a last step, we wait until the DOM is fully loaded
    bootstrapper.onFinalizationStage(() => new Promise(resolve => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                resolve();
            });
        } else {
            resolve();
        }
    }));
    bootstrapper.onFinalizationStage(registerSvelteSnippetLoader);

    return new Promise<void>(async (resolve) => {
        window.hawkiBootstrap = bootstrapper;
        if (Array.isArray(window.hawkiEarlyWaitUntilBootstrapQueue)) {
            for (const cb of window.hawkiEarlyWaitUntilBootstrapQueue) {
                await cb(bootstrapper);
            }
        }

        await bootstrapper.run();

        window.hawkiIsReady = true;
        if (Array.isArray(window.hawkiEarlyWaitUntilReadyQueue)) {
            for (const cb of window.hawkiEarlyWaitUntilReadyQueue) {
                await cb();
            }
        }

        resolve();
    });
}
