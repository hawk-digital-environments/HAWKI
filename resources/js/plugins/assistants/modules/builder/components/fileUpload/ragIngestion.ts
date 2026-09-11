import { getAssistant } from "$plugins/assistants/api/resources/assistantsClient";

/**
 * # RAG ingestion watching
 *
 * The knowledge-page's RAG workflow, kept separate from the plain upload
 * path in `FileUpload.svelte`: after a file upload succeeds while RAG is
 * enabled, ingestion into the knowledge base continues server-side (queued
 * job → RAG pipeline). The file only counts as "uploaded" once that pipeline
 * reports `ingested` — until then the UI shows it as "ingesting", and a
 * `failed`/`skipped` outcome makes the caller delete the attachment again.
 *
 * This module owns the data plane: it polls the assistant's
 * `assistant_attachments` include (which carries `rag_status`/`rag_error`)
 * and reports state changes per attachment uuid. UI decisions (statuses,
 * toasts, deletion) stay in `FileUpload.svelte`.
 */

/** Server-side RAG ingestion state of an attachment (`assistant_attachments.rag_status`). */
export type RagFileState = 'pending' | 'ingesting' | 'ingested' | 'failed' | 'skipped';

const RAG_FILE_STATES: readonly RagFileState[] = ['pending', 'ingesting', 'ingested', 'failed', 'skipped'];

export interface RagIngestionUpdate {
    /** The attachment's `uuid` — the id the attachment delete action expects. */
    uuid: string;
    state: RagFileState;
    /** Server-side failure reason (`rag_error`); `null` unless failed. */
    error: string | null;
}

export interface RagIngestionWatcher {
    /** Watch another attachment (e.g. right after its upload). Idempotent per uuid. */
    track(uuid: string, state: RagFileState): void;
    /** Stop polling and forget all tracked uuids (component teardown). */
    stop(): void;
}

function isRagFileState(value: unknown): value is RagFileState {
    return typeof value === 'string' && (RAG_FILE_STATES as readonly string[]).includes(value);
}

/** States the pipeline can still leave on its own — everything else is final. */
function isSettled(state: RagFileState): boolean {
    return state === 'ingested' || state === 'failed' || state === 'skipped';
}

/**
 * Polls the assistant's attachments and reports every observed `rag_status`
 * change for tracked uuids via `onUpdate` (each change exactly once). Polling
 * runs on a `setTimeout` chain — never overlapping requests — and stops by
 * itself once every tracked attachment reached a final state.
 *
 * Poll errors (network, 5xx) are swallowed after a warning; the next interval
 * retries. The watcher is plain stateful TS, no Svelte dependency.
 */
export function watchRagIngestion(
    assistantId: string,
    onUpdate: (update: RagIngestionUpdate) => void,
    intervalMs = 4000,
): RagIngestionWatcher {
    /** Last state reported per uuid; uuids are removed once settled. */
    const tracked = new Map<string, RagFileState>();
    let timer: ReturnType<typeof setTimeout> | null = null;
    let inFlight = false;

    function schedule(): void {
        if (timer !== null || tracked.size === 0) return;
        timer = setTimeout(() => {
            timer = null;
            void run();
        }, intervalMs);
    }

    async function run(): Promise<void> {
        if (tracked.size === 0 || inFlight) return;
        inFlight = true;
        let files: { uuid?: string; ragStatus?: string | null; ragError?: string | null }[] = [];
        try {
            const assistant = await getAssistant(assistantId, { include: ["assistant_attachments"] });
            files = assistant.files ?? [];
        } catch (err) {
            console.warn("RAG ingestion status poll failed; retrying next interval", err);
        } finally {
            inFlight = false;
        }

        for (const file of files) {
            const uuid = file.uuid;
            const state = file.ragStatus;
            if (!uuid || !isRagFileState(state) || !tracked.has(uuid)) continue;
            if (tracked.get(uuid) === state) continue;

            tracked.set(uuid, state);
            onUpdate({ uuid, state, error: file.ragError ?? null });

            if (isSettled(state)) {
                tracked.delete(uuid);
            }
        }

        schedule();
    }

    return {
        track(uuid: string, state: RagFileState): void {
            if (tracked.has(uuid)) return;
            tracked.set(uuid, state);
            schedule();
        },
        stop(): void {
            if (timer !== null) {
                clearTimeout(timer);
                timer = null;
            }
            tracked.clear();
        },
    };
}
