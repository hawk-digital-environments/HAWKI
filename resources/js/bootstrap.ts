import {markBootstrapCompleted, runBeforeReady, waitUntilReady} from '$lib/utils/waitUntilReady.js';
import {getAuthenticatedConnection, getConnection, getConnectionWithUserInfo, getRegisteringUserConnection, loadConnection} from '$lib/data/connection/connection.js';
import {autoRegisterConfigSchemas, getConfig, loadConfig} from '$lib/data/config/config.js';
import {initializeEcho} from '$lib/echo.js';
import {autoRegisterResourceSchemas} from '$lib/data/resources/resourceRegistry.js';
import {__, hasTranslation, loadTranslationLabels} from '$lib/utils/translator.js';
import {oldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';
import {buildStorageFileUrl} from '$lib/utils/storageFileProxy.js';
import {applyWaitingUntilReadyToMigrate, autoRegisterMigrations, waitUntilReadyToMigrate} from '$lib/data/migrations/migrator.js';
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

// Augment the global Window interface to include our globals, so that they can be accessed without TypeScript errors.
// WARNING: This is only here for legacy support! Do not use global variables in new code!
declare global {
    interface Window {
        __earlyWaitUntilReadyQueue: Array<() => Promise<void>>;
        getConnection: typeof getConnection;
        getAuthenticatedConnection: typeof getAuthenticatedConnection;
        getRegisteringUserConnection: typeof getRegisteringUserConnection;
        getConnectionWithUserInfo: typeof getConnectionWithUserInfo;
        getConfig: typeof getConfig;
        __: typeof __;
        hasTranslation: typeof hasTranslation;
        waitUntilReady: typeof waitUntilReady;
        oldUiBridge: typeof oldUiBridge;
        oldUiMessageHistory: typeof oldUiMessageHistory;
        OLD_UI_MIGHT_NEED_MIGRATION: boolean;
        waitUntilReadyToMigrate: typeof waitUntilReadyToMigrate;
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

const EARLY_BOOT_PRIORITY = 0;
const LATE_BOOT_PRIORITY = 50;
const FINAL_BOOT_PRIORITY = 100;

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
    window.waitUntilReady = waitUntilReady;
    window.waitUntilReadyToMigrate = waitUntilReadyToMigrate;
    window.buildStorageFileUrl = buildStorageFileUrl;
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
    runBeforeReady(loadConnection, EARLY_BOOT_PRIORITY);
    runBeforeReady(loadConfig, EARLY_BOOT_PRIORITY);

    // Special step because we need to sync the new migrations, with the old UI.
    // When the old ui tells us that it will run migrations (by setting the OLD_UI_MIGHT_NEED_MIGRATION flag)
    // we wait until the old UI has run its migrations before we go on with the remaining bootstrap steps.
    runBeforeReady(applyWaitingUntilReadyToMigrate, EARLY_BOOT_PRIORITY + 1);

    // Main bootstrap, these can be run in parallel, but they must be run after the connection and config are loaded.
    runBeforeReady(loadTranslationLabels);
    runBeforeReady(loadAiModels);
    runBeforeReady(loadAiToolsAndCapabilities);
    runBeforeReady(loadSystemPrompts);

    // Next, we initialize some globals, such as the Echo instance for real-time events.
    runBeforeReady(initializeEcho, LATE_BOOT_PRIORITY);

    // As a last step, we wait until the DOM is fully loaded
    runBeforeReady(() => new Promise(resolve => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                resolve();
            });
        } else {
            resolve();
        }
    }), FINAL_BOOT_PRIORITY);
    runBeforeReady(async () => registerSvelteSnippetLoader(), FINAL_BOOT_PRIORITY);

    // Inherit any before-ready tasks that were registered before we could start the bootstrap.
    if (Array.isArray(window.__earlyWaitUntilReadyQueue)) {
        window.__earlyWaitUntilReadyQueue.forEach(cb => waitUntilReady(cb));
        window.__earlyWaitUntilReadyQueue = [];
    }

    return markBootstrapCompleted();
}
