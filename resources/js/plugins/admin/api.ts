import z from 'zod';
import type { HawkiApp } from '$lib/kernel/HawkiApp.js';
import type { FetchResourceQuery } from '$lib/kernel/api/buildQueryString.js';
import { AdminContentSchema, type AdminRow } from './schemas/admin-content.js';
import type { SectionId } from './sections.js';

type Client = Pick<HawkiApp, 'restApi' | 'uriBuilder'>;
const WriteResult = z.object({ id: z.string() });
const ActionResult = z
    .object({
        models: z.array(z.object({ model_id: z.string(), label: z.string() })).optional(),
        model: z.record(z.string(), z.unknown()).optional(),
        tools: z.array(z.string()).optional(),
        tokens: z.array(z.record(z.string(), z.unknown())).optional()
    })
    .passthrough();

function path(section: SectionId, id?: string) {
    return `admin/${section}${id === undefined ? '' : `/${encodeURIComponent(id)}`}`;
}

export async function readAdmin(client: Client, section: SectionId, query: FetchResourceQuery, signal?: AbortSignal) {
    const response = await client.restApi.fetch(client.uriBuilder.jsonApiUri(path(section), query), {
        method: 'GET',
        signal,
        schema: z.object({ content: AdminContentSchema })
    });
    return response.content;
}

export function createAdmin(client: Client, section: SectionId, values: Record<string, unknown>) {
    return client.restApi.fetch(client.uriBuilder.jsonApiUri(path(section)), {
        method: 'POST',
        body: JSON.stringify({ values }),
        schema: WriteResult
    });
}

export function updateAdmin(client: Client, section: SectionId, row: AdminRow, values: Record<string, unknown>) {
    return client.restApi.fetch(client.uriBuilder.jsonApiUri(path(section, row.id)), {
        method: 'PATCH',
        body: JSON.stringify({ version: row._version, values }),
        schema: WriteResult
    });
}

export function deleteAdmin(client: Client, section: SectionId, row: AdminRow) {
    return client.restApi.fetch(client.uriBuilder.jsonApiUri(path(section, row.id)), {
        method: 'DELETE',
        body: JSON.stringify({ version: row._version })
    });
}

export function runAdmin(
    client: Client,
    section: SectionId,
    action: string,
    id?: string,
    body?: Record<string, unknown>
) {
    return client.restApi.fetch(
        client.uriBuilder.jsonApiUri(`${path(section, id)}/actions/${encodeURIComponent(action)}`),
        {
            method: action === 'tokens' ? 'GET' : 'POST',
            body: body === undefined ? undefined : JSON.stringify(body),
            schema: ActionResult
        }
    );
}
