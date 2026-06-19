import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import {SendMessageStatus} from '$lib/components/chat/composer/contexts/sending/SendMessageStatus.svelte.js';
import {createResponseReader, type ResponseBody, type ResponseReader, SendMessageResponse} from '$lib/components/chat/composer/contexts/sending/SendMessageResponse.svelte.js';
import type {MessageSenderTransportInterface} from '$lib/components/chat/composer/contexts/sending/transport/MessageSenderTransportInterface.js';
import {__} from '$lib/utils/translator.js';

export class MessageSender {
    constructor(
        private transport: MessageSenderTransportInterface
    ) {
    }

    public send(context: ComposerContext): SendMessageStatus {
        // This promise resolves as soon as the message has been sent. If it resolves it contains the response tracker listener,
        // which allows the sender to listen for the body/chunks of the response, as well as any errors that might occur while processing the response.
        let responsePromiseResolve: (response: ResponseReader) => void = () => void 0;
        const responsePromise = new Promise<ResponseReader>((resolve) => {
            responsePromiseResolve = resolve;
        });

        const status = new SendMessageStatus(
            context.attachments.assignedUuids,
            responsePromise
        );

        const response = new SendMessageResponse();
        const responseListener = createResponseReader(response);

        let isWaitingForResponse = false;

        const setResponse = (body: ResponseBody) => response.triggerReceived(body);
        const setResponseFailed = (errorMessage: string) => response.triggerError(errorMessage);

        const waitForResponse = (
            handler: (response: SendMessageResponse) => void | Promise<void>
        ) => {
            if (isWaitingForResponse) {
                console.warn('waitForResponse was called multiple times. Only the first call will be used.');
                return;
            }

            isWaitingForResponse = true;

            (async () => {
                try {
                    await handler(response);
                } catch (error) {
                    console.error('An error occurred while handling the response:', error);
                    response.triggerError(__('chat.composer.sending.responseError'));
                }
            })();
        };

        (async () => {
            try {
                await this.transport.sendMessage({
                    context,
                    status,
                    setResponse,
                    setResponseFailed,
                    waitForResponse
                });

                if (!isWaitingForResponse && !response.done) {
                    console.warn('Implementation issue: The sendMessage handler finished without waiting for a response or marking the response as done.');
                    response.triggerReceived();
                }
            } catch (error) {
                console.error('An error occurred while sending the message:', error);
                status.addSendIssue(__('chat.composer.sending.sendError'));
                response.triggerError(__('chat.composer.sending.sendError'));
            }

            responsePromiseResolve(responseListener);
        })();

        return status;
    }
}
