import type {MailhogContext} from './MailhogContext.ts';

declare module '@/Context.ts' {
    interface Context {
        readonly mailhog: MailhogContext;
    }
}
