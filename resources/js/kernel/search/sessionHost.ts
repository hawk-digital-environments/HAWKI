import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {SearchProviderDefinition, SearchRegistry} from './searchRegistry.js';
import type {SharedSearchIndex} from './sharedIndex.js';
import type {SearchProviderError} from './types.js';
import type {SearchScores} from './searchEngine.js';

/** Runtime capabilities shared by independent search sessions. */
export interface SearchSessionHost {
    readonly app: HawkiApp;
    readonly registry: SearchRegistry;
    readonly index: SharedSearchIndex;
    readonly identity: string | null;
    readonly providers: readonly {definition: SearchProviderDefinition; signal: AbortSignal}[];
    readonly errors: readonly SearchProviderError[];
    groupLabel(groupId: string): string;
    retryStatic(providerId: string): void;
    queryWorker(query: string, signal: AbortSignal): Promise<{revision: number; scores: SearchScores}>;
    retryWorker(): void;
}
