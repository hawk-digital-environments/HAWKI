import type {MessageSenderTransportInterface, MessageSenderTransportOptions} from '$lib/components/chat/composer/contexts/sending/transport/MessageSenderTransportInterface.js';
import type {OldUiBridge} from '$lib/oldUi/OldUiBridge.svelte.js';

export class OldUiBridgeTransport implements MessageSenderTransportInterface {
    constructor(
        private readonly oldUiBridge: OldUiBridge
    ) {
    }

    public sendMessage(opt: MessageSenderTransportOptions): Promise<void> {
        const context = opt.context;
        return this.oldUiBridge.triggerSendMessage({
            ...opt,
            mode: context.mode.state,
            systemPrompt: context.systemPrompt,
            model: context.model.current,
            contextType: context.type,
            message: context.message,
            attachments: context.attachments.list,
            tools: context.tools.active,
            parameters: {
                temp: context.modelParameters.get('temperature'),
                top_p: context.modelParameters.get('top_p')
            }
        });
    }
}
