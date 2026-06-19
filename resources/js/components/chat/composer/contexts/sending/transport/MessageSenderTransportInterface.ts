import type {SendMessageStatus} from '$lib/components/chat/composer/contexts/sending/SendMessageStatus.svelte.js';
import {type ResponseBody, SendMessageResponse} from '$lib/components/chat/composer/contexts/sending/SendMessageResponse.svelte.js';
import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';

export type TransportSetResponse = (body: ResponseBody) => void;
export type TransportSetResponseFailed = (error: string) => void;
export type TransportWaitForResponse = (
    handler: (response: SendMessageResponse) => void | Promise<void>
) => void;

export interface MessageSenderTransportOptions {
    context: ComposerContext;
    status: SendMessageStatus;
    setResponse: TransportSetResponse;
    setResponseFailed: TransportSetResponseFailed;
    waitForResponse: TransportWaitForResponse;
}

export interface MessageSenderTransportInterface {
    sendMessage(opt: MessageSenderTransportOptions): Promise<void>;
}
