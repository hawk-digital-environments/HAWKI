import type { AiModel } from '../../../resources/js/plugins/core/schemas/resources/ai-models.schema.js';
import type { AiTool } from '../../../resources/js/plugins/core/schemas/resources/ai-tools.schema.js';
import CapabilitySchema from '../../../resources/js/plugins/core/schemas/resources/ai-tools-capabilities.schema.js';
import { combineToolsAndCapabilities } from '../../../resources/js/plugins/core/stores/aiToolStoreData.js';

export const translator: any = { translate: (key: string) => key, __: (key: string) => key };
export const model: AiModel = {
    id: '7',
    active: true,
    model_id: 'test-model',
    label: 'Test',
    tool_ids: [11, 12],
    settings: { tool_calling: true },
    native_capabilities: [],
    input: ['text'],
    output: ['text'],
    model_type: 'chat',
    status: 'online',
    demand: 'low',
    parameters: null,
    flags: [],
    created_at: '',
    updated_at: '',
    limits: null,
    pricing: null
};
/** Tool calling switched off: no snapshot that uses tools can run on it. */
export const noToolCallingModel: AiModel = {
    ...model,
    id: '8',
    model_id: 'no-tools-model',
    label: 'No Tools',
    settings: {}
};
/** Tool calling on, but the web-search tool is not assigned to it. */
export const foreignToolModel: AiModel = {
    ...model,
    id: '9',
    model_id: 'foreign-tools-model',
    label: 'Foreign Tools',
    tool_ids: []
};
/** Distinct fallback model for the "picked model is gone" notice. */
export const fallbackModel: AiModel = {
    ...model,
    id: '10',
    model_id: 'fallback-model',
    label: 'Fallback',
    tool_ids: [11, 12]
};
export const tool: AiTool = {
    id: '11',
    name: 'search',
    description: 'Search',
    status: 'online',
    capability_key: 'web_search',
    created_at: '',
    updated_at: ''
};
export const standalone: AiTool = { ...tool, id: '12', name: 'documents', capability_key: null };
export const capability = CapabilitySchema.parse({
    id: 'web_search',
    title_label: 'search',
    description_label: null,
    icon_path: ''
});

/** The `AiToolStore` shape the transport and snapshot validation read. */
export function catalog(tools = [tool, standalone], capabilities = [capability]): any {
    return { tools: combineToolsAndCapabilities(translator, tools, capabilities) };
}

/** An `AiModelStore` stand-in that resolves models by id, with `fallback` for unknown ids. */
export function modelStoreFor(models: AiModel[], fallback: AiModel = models[0]): any {
    return {
        models,
        getOneById: (id: string) => models.find((candidate) => candidate.model_id === id) ?? null,
        getModelByIdOrFallback: (id: string | null) =>
            models.find((candidate) => candidate.model_id === id) ?? fallback,
        getSystemModelByType: () => fallback
    };
}
