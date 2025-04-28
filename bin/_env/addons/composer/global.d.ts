import type {ComposerContext} from './ComposerContext';

declare module '@/Context.ts' {
    interface Context {
        readonly composer: ComposerContext;
    }
}
