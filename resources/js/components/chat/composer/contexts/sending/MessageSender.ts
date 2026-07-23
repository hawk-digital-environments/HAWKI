import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
import {SendMessageStatus} from '$lib/components/chat/composer/contexts/sending/SendMessageStatus.svelte.js';
import {createResponseReader, type ResponseBody, type ResponseReader, SendMessageResponse} from '$lib/components/chat/composer/contexts/sending/SendMessageResponse.svelte.js';
import type {MessageSenderTransportInterface} from '$lib/components/chat/composer/contexts/sending/transport/MessageSenderTransportInterface.js';
import {__} from '$lib/utils/translator.js';

/**
 * Orchestrates the message-send flow.
 *
 * On `send()`:
 * 1. Creates a `SendMessageStatus` that the UI uses to track progress.
 * 2. Delegates the actual HTTP/transport work to the injected `MessageSenderTransportInterface`.
 * 3. Once the transport resolves, surfaces a `ResponseReader` through
 *    `status.response` so the UI can subscribe to body chunks and completion.
 *
 * Error handling: transport errors and unhandled promise rejections are caught
 * here and surfaced as send issues on the status rather than propagating as
 * uncaught exceptions.
 */
export class MessageSender {
    constructor(
        private transport: MessageSenderTransportInterface
    ) {
    }

    /**
     * Starts a send operation. Returns a `SendMessageStatus` immediately —
     * the actual request runs asynchronously. The caller should store the
     * status on the context and clear it once `status.response` resolves and
     * the response body has been fully received.
     */
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

        response.onError((error) => {
            status.addSendIssue(error);
        });

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
                    if (!response.done) {
                        response.triggerError(__('chat.composer.sending.responseError'));
                    }
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
