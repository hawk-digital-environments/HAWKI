/**
 * The share dialog's selection state: everyone it knows about (the loaded
 * share list plus seen search results), who of them is selected, and whether
 * the selection has drifted from the server's baseline — the dirty check
 * behind the save button. Extracted from ShareDialog.svelte so the state
 * machine is testable without a DOM.
 */

/** One person the dialog knows about. */
export interface SharePerson {
    userId: number;
    displayName: string;
    username: string;
    avatar: string | null;
    selected: boolean;
    canEdit: boolean;
}

/** A share entry as answered by `actions/share` (see assistantsClient). */
export interface ShareBaselineEntry {
    userId: number;
    displayName: string;
    username: string;
    canEdit: boolean;
    avatar: string | null;
}

/** A user as offered by the lookup (see usersClient). */
export interface ShareCandidate {
    userId: number;
    displayName: string;
    username: string;
    avatar: string | null;
}

/** Canonical signature of a share set — two sets are equal iff these match. */
function signature(entries: ReadonlyArray<{userId: number; canEdit: boolean}>): string {
    return entries.map((entry) => `${entry.userId}:${entry.canEdit ? 1 : 0}`).sort().join('|');
}

export function createShareSelection() {
    let people = $state<SharePerson[]>([]);
    let baseline = $state<ShareBaselineEntry[]>([]);

    return {
        /** Adopts the server's share list as the new baseline and selection. */
        adopt(entries: ShareBaselineEntry[]): void {
            baseline = entries;
            people = entries.map((entry): SharePerson => ({
                userId: entry.userId,
                displayName: entry.displayName,
                username: entry.username,
                avatar: entry.avatar,
                selected: true,
                canEdit: entry.canEdit
            }));
        },

        /** Makes search results known (unselected) persons; idempotent. */
        seed(candidates: ReadonlyArray<ShareCandidate>): void {
            const known = new Set(people.map((person) => person.userId));
            const seeded = candidates
                .filter((candidate) => !known.has(candidate.userId))
                .map((candidate): SharePerson => ({
                    userId: candidate.userId,
                    displayName: candidate.displayName,
                    username: candidate.username,
                    avatar: candidate.avatar,
                    selected: false,
                    canEdit: false
                }));
            if (seeded.length) people = [...people, ...seeded];
        },

        toggle(userId: number): void {
            const person = this.find(userId);
            if (person) person.selected = !person.selected;
        },

        find(userId: number): SharePerson | undefined {
            return people.find((person) => person.userId === userId);
        },

        get people(): ReadonlyArray<SharePerson> {
            return people;
        },

        get baseline(): ReadonlyArray<ShareBaselineEntry> {
            return baseline;
        },

        get selected(): ReadonlyArray<SharePerson> {
            return people.filter((person) => person.selected);
        },

        /** Whether the selection differs from the server's baseline. */
        get dirty(): boolean {
            return signature(this.selected) !== signature(baseline);
        },

        /** The payload for the sync call — the full desired share list. */
        get payload(): ReadonlyArray<{userId: number; canEdit: boolean}> {
            return this.selected.map((person): {userId: number; canEdit: boolean} => ({
                userId: person.userId,
                canEdit: person.canEdit
            }));
        }
    };
}
