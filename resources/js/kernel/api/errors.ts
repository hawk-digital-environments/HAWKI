export interface ApiTransportServerErrorMessage {
    title: string;
    detail: string;
}

export class ApiTransportError extends Error {
    constructor(
        readonly status: number,
        readonly errors: Array<ApiTransportServerErrorMessage>,
        readonly body: unknown,
        message: string
    ) {
        super(message);
    }
}
