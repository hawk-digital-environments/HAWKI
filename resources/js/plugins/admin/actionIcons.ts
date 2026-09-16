import type { IconComponent } from '$lib/components/ui/icons/index.js';
import RefreshIcon from '$lib/components/ui/icons/iconset/RefreshIcon.svelte';
import PencilEdit01Icon from '$lib/components/ui/icons/iconset/PencilEdit01Icon.svelte';
import Delete02Icon from '$lib/components/ui/icons/iconset/Delete02Icon.svelte';
import UndoIcon from '$lib/components/ui/icons/iconset/UndoIcon.svelte';
import FileExportIcon from '$lib/components/ui/icons/iconset/FileExportIcon.svelte';
import FileImportIcon from '$lib/components/ui/icons/iconset/FileImportIcon.svelte';
import Plug01Icon from '$lib/components/ui/icons/iconset/Plug01Icon.svelte';
import Search01Icon from '$lib/components/ui/icons/iconset/Search01Icon.svelte';
import CloudSyncIcon from '$lib/components/ui/icons/iconset/CloudSyncIcon.svelte';
import Key01Icon from '$lib/components/ui/icons/iconset/Key01Icon.svelte';
import CancelCircleIcon from '$lib/components/ui/icons/iconset/CancelCircleIcon.svelte';
import Activity01Icon from '$lib/components/ui/icons/iconset/Activity01Icon.svelte';
import ReloadIcon from '$lib/components/ui/icons/iconset/ReloadIcon.svelte';

/**
 * Icons for the entries of the admin action menus, keyed by action id.
 * Built-in workspace entries (`reload`, `edit`, `delete`, `reset`, `export`) share the
 * same map as the section specific `pageActions` / `rowActions` ids.
 */
export const adminActionIcons: Record<string, IconComponent> = {
    'reload': RefreshIcon,
    'edit': PencilEdit01Icon,
    'delete': Delete02Icon,
    'reset': UndoIcon,
    'export': FileExportIcon,
    'import': FileImportIcon,
    'test': Plug01Icon,
    'discover': Search01Icon,
    'refresh': CloudSyncIcon,
    'tokens': Key01Icon,
    'revoke-tokens': CancelCircleIcon,
    'check-ai-status': Activity01Icon,
    'check-status': Activity01Icon,
    'retry-job': ReloadIcon,
    'flush-jobs': Delete02Icon
};
