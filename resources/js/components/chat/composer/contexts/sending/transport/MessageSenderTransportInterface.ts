import type {SendMessageStatus} from '$lib/components/chat/composer/contexts/sending/SendMessageStatus.svelte.js';
import {type ResponseBody, SendMessageResponse} from '$lib/components/chat/composer/contexts/sending/SendMessageResponse.svelte.js';
import type {ComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';

export type TransportSetResponse = (body: ResponseBody) => void;
export type TransportSetResponseFailed = (error: string) => void;
export type TransportWaitForResponse = (
    handler: (response: SendMessageResponse) => void | Promise<void>
) => void;

/**
 * Options passed by `MessageSender` to the transport's `sendMessage` method.
 *
 * The transport must signal how the response arrives using exactly one of the
 * three response callbacks:
 *
 * - **`setResponse(body)`** — the full response body is already available
 *   (non-streaming). Call once and return.
 * - **`setResponseFailed(error)`** — the send failed with an error message.
 * - **`waitForResponse(handler)`** — the response will arrive asynchronously
 *   (e.g. a streaming connection). The handler receives the `SendMessageResponse`
 *   write surface to push chunks via `triggerBodyChunk()` and finalize via
 *   `triggerReceived()` / `triggerError()`. Only one call to `waitForResponse`
 *   is allowed per send; subsequent calls are no-ops with a console warning.
 */
export interface MessageSenderTransportOptions {
    context: ComposerContext;
    status: SendMessageStatus;
    setResponse: TransportSetResponse;
    setResponseFailed: TransportSetResponseFailed;
    waitForResponse: TransportWaitForResponse;
}

/** Pluggable transport interface for message delivery. */
export interface MessageSenderTransportInterface {
    sendMessage(opt: MessageSenderTransportOptions): Promise<void>;
}
