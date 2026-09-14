import z from 'zod';
import type { HawkiApp } from '$lib/kernel/HawkiApp.js';

export const SvglIconSchema = z.object({
    id: z.number().int(),
    title: z.string(),
    light: z.string().url(),
    dark: z.string().url().nullable()
});
export type SvglIcon = z.infer<typeof SvglIconSchema>;
type Client = Pick<HawkiApp, 'restApi' | 'uriBuilder'>;

export async function loadProviderIcons(client: Client, signal: AbortSignal, search = '') {
    const result = await client.restApi.fetch(
        client.uriBuilder.jsonApiUri('admin/providers/actions/icons', { filter: { search } }),
        {
            method: 'GET',
            signal,
            schema: z.object({ icons: z.array(SvglIconSchema) })
        }
    );
    return result.icons;
}

export async function uploadProviderIcon(client: Client, file: File, signal: AbortSignal) {
    const body = new FormData();
    body.append('image', file);
    const result = await client.restApi.fetch(client.uriBuilder.jsonApiUri('admin/providers/actions/icon-upload'), {
        method: 'POST',
        body,
        signal,
        schema: z.object({
            icon: z.object({ source: z.literal('upload'), title: z.string(), svg: z.string(), svg_dark: z.null() })
        })
    });
    return result.icon;
}

export function svgPreview(svg: unknown): string | undefined {
    return typeof svg === 'string' ? `data:image/svg+xml,${encodeURIComponent(svg)}` : undefined;
}
