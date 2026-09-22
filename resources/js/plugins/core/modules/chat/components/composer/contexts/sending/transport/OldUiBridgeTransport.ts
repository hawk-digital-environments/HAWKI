import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {validatedToolSnapshot} from '$plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';
import type {MessageSenderTransportInterface, MessageSenderTransportOptions} from '$plugins/core/modules/chat/components/composer/contexts/sending/transport/MessageSenderTransportInterface.js';
import type {OldUiBridge} from '$lib/legacy/OldUiBridge.svelte.js';

/** Sends through the legacy UI with a captured selection and shared authorization checks. */
export class OldUiBridgeTransport implements MessageSenderTransportInterface {
    constructor(
        private readonly oldUiBridge: OldUiBridge,
        private readonly app: HawkiApp
    ) {
    }

    public async sendMessage(opt: MessageSenderTransportOptions): Promise<void> {
        const context = opt.context;
        let tools;
        try {
            tools = context.tools.validatedActive();
        } catch (error) {
            const code = error instanceof Error ? error.message : '';
            opt.setResponseFailed(this.app.translator.__(code === 'TOOL_AUTHORIZATION_REFRESHING'
                ? 'chat.tools.authorizationRefreshing' : code === 'TOOL_UNAVAILABLE'
                    ? 'chat.tools.unavailable' : 'chat.tools.accessDenied'));
            return;
        }
        const toolTransfers = Object.freeze(tools.map(tool => tool.toTransferString()));
        const initialModel = context.model.current;
        const initialModelId = initialModel?.id;
        const initialModelName = initialModel?.model_id;
        const requiresModel = !context.mode.isEdit && (context.type === 'aiConv' || context.containsAiHandle || toolTransfers.length > 0);
        const sessionGeneration = this.app.stores.get('ai-tools').sessionGeneration;
        const actor = this.app.connection.isAuthenticated ? this.app.connection.userinfo.id : null;
        const authorization = {
            validate: (): boolean => {
                try {
                    if (!this.app.connection.isAuthenticated || this.app.connection.userinfo.id !== actor || this.app.stores.get('ai-tools').sessionGeneration !== sessionGeneration) throw new Error('TOOL_ACCESS_DENIED');
                    const model = this.app.stores.get('ai-models').getOneById(initialModelId ?? '');
                    if (requiresModel && (!model || model.model_id !== initialModelName)) throw new Error('TOOL_UNAVAILABLE');
                    const current = validatedToolSnapshot(toolTransfers, this.app.stores.get('ai-tools'), model!);
                    if (current.some((value, index) => value !== toolTransfers[index])) throw new Error('TOOL_ACCESS_DENIED');
                    return true;
                } catch (error) {
                    const code = error instanceof Error ? error.message : '';
                    opt.setResponseFailed(this.app.translator.__(opt.status.accepted ? 'chat.tools.messageAccepted' : code === 'TOOL_AUTHORIZATION_REFRESHING'
                        ? 'chat.tools.authorizationRefreshing' : code === 'TOOL_UNAVAILABLE'
                            ? 'chat.tools.unavailable' : 'chat.tools.accessDenied'));
                    return false;
                }
            },
            request: async (url: string, data: unknown, signal: AbortSignal): Promise<Response> => {
                const body = await this.app.restApi.fetch(url, {method: 'POST', body: JSON.stringify(data), responseType: 'stream', signal});
                return new Response(body);
            },
            refresh: (): void => {void this.app.refreshConnection().catch(() => undefined);}
        };
        if (!authorization.validate()) return;
        return this.oldUiBridge.triggerSendMessage({
            ...opt,
            mode: context.mode.state,
            systemPrompt: context.systemPrompt,
            model: initialModel,
            contextType: context.type,
            message: context.message,
            containsAiHandle: context.containsAiHandle,
            assistantHandle: context.addressedAssistantHandle,
            attachments: [...context.attachments.list],
            tools,
            toolTransfers,
            authorization,
            parameters: {
                temp: context.modelParameters.get('temperature'),
                top_p: context.modelParameters.get('top_p')
            }
        });
    }
}
