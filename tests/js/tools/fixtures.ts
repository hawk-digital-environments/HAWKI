import type {AiModel} from '../../../resources/js/plugins/core/schemas/resources/ai-models.schema.js';
import type {AiTool} from '../../../resources/js/plugins/core/schemas/resources/ai-tools.schema.js';
import CapabilitySchema from '../../../resources/js/plugins/core/schemas/resources/ai-tools-capabilities.schema.js';
import {combineToolsAndCapabilities} from '../../../resources/js/plugins/core/stores/aiToolStoreData.js';

export const translator: any = {translate: (key: string) => key, __: (key: string) => key};
export const model: AiModel = {
    id: '7', model_id: 'test-model', label: 'Test', tool_ids: [11, 12], settings: {tool_calling: true},
    native_capabilities: ['web_search'], input: ['text'], output: ['text'], model_type: 'chat',
    status: 'online', demand: 'low', parameters: null, flags: [], created_at: '', updated_at: '',
    limits: null, pricing: null
};
export const tool: AiTool = {
    id: '11', name: 'search', description: 'Search', status: 'online', capability_key: 'web_search',
    created_at: '', updated_at: ''
};
export const standalone: AiTool = {...tool, id: '12', name: 'documents', capability_key: null};
export const capability = CapabilitySchema.parse({id: 'web_search', title_label: 'search', description_label: null, icon_path: '', native_model_ids: ['7']});
export function catalog(tools = [tool, standalone], capabilities = [capability]): any {
    return {tools: combineToolsAndCapabilities(translator, tools, capabilities), authorizationState: 'ready'};
}
export function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: unknown) => void;
    const promise = new Promise<T>((res, rej) => {resolve = res; reject = rej;});
    return {promise, resolve, reject};
}
