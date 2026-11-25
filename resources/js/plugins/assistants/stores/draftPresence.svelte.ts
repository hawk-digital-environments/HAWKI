import {listAssistants} from '$plugins/assistants/api/resources/assistantsClient';
import {ReleaseMode} from '$plugins/assistants/types/assistant/ReleaseMode';

/**
 * Whether the current user has any drafts of their own — decides if the
 * sidebar shows the "Drafts" section. `null` until the first check lands.
 */
class DraftPresence {
    hasDrafts = $state<boolean | null>(null);
    private ticket = 0;

    async refresh(): Promise<void> {
        const ticket = ++this.ticket;
        try {
            const {assistants, pagination} = await listAssistants({
                filter: {release_stage: ReleaseMode.DRAFT, is_creator: true},
                page: {number: 1, size: 1}
            });
            if (ticket !== this.ticket) return;
            this.hasDrafts = (pagination?.itemCount ?? assistants.length) > 0;
        } catch {
            // Keep the last known state.
        }
    }
}

export const draftPresence = new DraftPresence();
