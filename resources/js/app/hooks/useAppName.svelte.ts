import {useConfig} from '$lib/app/hooks/useConfig.svelte.js';

/**
 * The name this deployment gives the application (`APP_NAME`, editable in the
 * admin panel). Pass it to translations as `:appName` instead of writing
 * "HAWKI" into a user-facing string.
 */
export function useAppName(): string {
    return useConfig().branding?.appName || 'HAWKI';
}
