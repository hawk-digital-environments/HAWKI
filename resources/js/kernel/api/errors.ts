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
        const code = this.errors[0]?.code;
        if (code) return code;
        return this.body && typeof this.body === 'object' && 'code' in this.body && typeof this.body.code === 'string'
            ? this.body.code : undefined;
    }
}
