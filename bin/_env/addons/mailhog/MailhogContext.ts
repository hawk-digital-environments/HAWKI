import type {Context} from '@/Context.ts';

export class MailhogContext {
    private readonly _context: Context;

    public constructor(context: Context) {
        this._context = context;
    }

    public get port(): string {
        return this._context.env.getGlobal('MAILHOG_PORT', '8025');
    }
}
