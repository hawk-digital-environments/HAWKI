import {z} from 'zod';
import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte.js';

export interface UseRouteMetaOptions<TSchema extends z.ZodType> {
    /**
     * Schema describing the meta this component expects. The return type is
     * inferred from it, and the meta is validated against it on every read —
     * a mismatch is a wiring bug between the route registration and the
     * component, so it throws rather than silently handing back garbage.
     *
     * Give optional fields a `.default()` so a route that omits them still
     * parses.
     */
    schema: TSchema;
    /** Router to read from; defaults to the nearest one, same as `useRouter()`. */
    router?: string;
}

export interface RouteMetaHandle<TMeta> {
    /**
     * The validated meta of the route currently rendered.
     *
     * A getter, not a plain value: a layout outlives the pages inside it, so
     * reading through it is what makes the meta update when navigation swaps
     * one page for a sibling.
     */
    readonly current: TMeta;
}

/**
 * Typed, reactive access to the matched route's `meta`.
 *
 * Meta belongs to the route, so the page *and* every layout wrapping it see
 * the same object — a layout can render `meta.current.title` in its header and
 * have it follow the page inside it.
 *
 * @example
 * // routeMeta.ts, next to the page component
 * export const chatMetaSchema = z.object({title: z.string()});
 * export type ChatMeta = z.infer<typeof chatMetaSchema>;
 *
 * // in the registrar
 * registrar.lazyRoute<ChatMeta>('/', loader, {meta: {title: 'Chat'}});
 *
 * // in the page or any layout above it
 * const meta = useRouteMeta({schema: chatMetaSchema});
 * // ... {meta.current.title}
 */
export function useRouteMeta<TSchema extends z.ZodType>(
    options: UseRouteMetaOptions<TSchema>
): RouteMetaHandle<z.infer<TSchema>> {
    // Must happen during component init — `useRouter` reads from context.
    const router = useRouter(options.router);

    return {
        get current() {
            const parsed = options.schema.safeParse(router.meta ?? {});
            if (!parsed.success) {
                throw new Error(
                    `Route meta of "${router.path}" does not match the requested schema: ${parsed.error.message}`
                );
            }
            return parsed.data;
        }
    };
}
