import { getContext, setContext } from 'svelte';
import type { AdminContent, AdminRow } from './schemas/admin-content.js';

export interface AdminAction {
    id: string;
    confirm?: boolean;
    destructive?: boolean;
}

export interface AdminWorkspaceContext {
    content: AdminContent | null;
    loading: boolean;
    busy: boolean;
    /** Ids of rows currently being saved through `update`; only those rows are locked, not the workspace. */
    updating: string[];
    dialogOpen: boolean;
    reload: () => Promise<void>;
    action: (action: AdminAction, id?: string, trigger?: HTMLElement | null) => void;
    edit: (row: AdminRow, trigger: HTMLElement | null) => void;
    reset: (row: AdminRow, trigger: HTMLElement | null) => void;
    /** Saves `changes` merged into the existing row, like an editor submission that touched only those fields. */
    update: (row: AdminRow, changes: Record<string, unknown>) => Promise<void>;
}

const contextKey = Symbol('admin-workspace');

/** Called by AdminWorkspace so components rendered inside it (e.g. custom cells) can reach the workspace. */
export function provideAdminWorkspace(read: () => AdminWorkspaceContext): void {
    setContext(contextKey, read);
}

/** Returns a getter for the surrounding AdminWorkspace's context; read it lazily to stay reactive. */
export function useAdminWorkspace(): () => AdminWorkspaceContext {
    const read = getContext<(() => AdminWorkspaceContext) | undefined>(contextKey);
    if (!read) throw new Error('useAdminWorkspace() must be used inside an AdminWorkspace.');
    return read;
}
