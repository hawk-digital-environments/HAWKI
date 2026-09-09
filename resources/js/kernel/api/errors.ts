export interface ApiTransportServerErrorMessage {
    code?: string;
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

    public get code(): string | undefined {
        return this.errors[0]?.code;
    }
}
