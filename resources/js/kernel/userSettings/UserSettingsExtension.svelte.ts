import type { z } from 'zod';
import type { Bootstrapper } from '$lib/kernel/Bootstrapper.js';
import type { HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals } from '$lib/kernel/HawkiApp.js';
import type { HawkiUserSettingsSchemas } from '$lib/kernel/extendableTypes.js';
import { createUserSettingsSchemaRegistrar } from '$lib/kernel/userSettings/userSettingsSchemaRegistrar.js';
import { debounce } from '$lib/utils/debounce.js';
import { updateObject } from '$lib/utils/objects.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        readonly userSettings: WithoutAppExtensionInternals<UserSettingsExtension>;
    }

    /**
     * Fired by the settings UI after the user's locale preference has been
     * persisted to the user settings. The client extension's listener
     * refreshes the connection first (so `connection.locale` is fresh), then
     * later listeners — translation labels, locale-dependent components —
     * pick up the new value.
     */
    interface HawkiAsyncEvents {
        localeChanged: void;
    }
}

/**
 * Fetches, validates, and reactively exposes per-user (or per-session) settings
 * from the `user-settings` JSON:API resource.
 *
 * Each namespace maps to one resource (resource id = namespace, e.g.
 * `'hawki-core'`). Within a namespace, settings are grouped by each settings
 * class's "public key" (e.g. `core` → `{locale, theme, timezone}`). The
 * resource looks like `{"id": "hawki-core", "core": {...}}` on the wire.
 *
 * The extension fetches all namespace resources during the bootstrapper's
 * `preparation` stage via a single `GET /user-settings` collection request,
 * parsing each namespace against its registered Zod schema on first
 * `get(namespace)` access. Parsed results are cached and kept reactive across
 * refreshes (and authentication transitions) through the same identity-
 * preservation trick as {@link ConfigurationExtension}.
 *
 * Because user settings are per-user (guests get session-backed defaults),
 * there is no server-side seeding — an empty/absent response means every
 * field falls back to its Zod default. This makes the extension safe to call
 * during early bootstrap.
 */
export class UserSettingsExtension implements HawkiAppExtension {
    private schemaRegistry = new Map<string, z.ZodTypeAny>();
    private raw = $state<Record<string, Record<string, unknown>> | null>(null);
    private parsedCache = $state<Partial<Record<keyof HawkiUserSettingsSchemas, unknown>>>({});
    private app: UnfinishedHawkiApp | null = null;
    /** @var Map<string, (partial: Record<string, unknown>) => void> keyed by "<namespace>|<publicKey>|<debounceTime>" */
    private debouncedSaves = new Map<string, (partial: Record<string, unknown>) => void>();

    public get namespaces(): (keyof HawkiUserSettingsSchemas)[] {
        return Array.from(this.schemaRegistry.keys()) as (keyof HawkiUserSettingsSchemas)[];
    }

    public get(): z.infer<HawkiUserSettingsSchemas['hawki-core']>;
    public get<N extends keyof HawkiUserSettingsSchemas>(namespace: N): z.infer<HawkiUserSettingsSchemas[N]>;
    public get<N extends keyof HawkiUserSettingsSchemas>(namespace?: N): z.infer<HawkiUserSettingsSchemas[N]> {
        const ns = (namespace ?? 'hawki-core') as N;
        if (ns in this.parsedCache) {
            const cached = this.parsedCache[ns];
            return cached as z.infer<HawkiUserSettingsSchemas[N]>;
        }

        const schema = this.schemaRegistry.get(ns as string);
        if (!schema) {
            throw new Error(`No user-settings schema registered for namespace: ${String(ns)}`);
        }

        const parsed = schema.parse(this.raw?.[ns as string] ?? {});
        this.parsedCache[ns] = parsed;
        return this.parsedCache[ns] as z.infer<HawkiUserSettingsSchemas[N]>;
    }

    /**
     * Refreshes all user-settings namespace resources in one request.
     *
     * Fetches the `user-settings` JSON:API collection (`validateSchema: false`
     * because user-settings schemas are per-namespace, not a single
     * resource-level schema registered in {@link HawkiResourceSchemas}).
     * Handles an empty/absent response gracefully (falls back to `{}`, which
     * means every field resolves to its Zod default).
     *
     * Already-returned namespace objects are updated in place via
     * {@link updateObject} so components holding `const s = useUserSettings()`
     * observe refreshed values after authentication transitions.
     */
    public async refresh(): Promise<void> {
        if (!this.app) {
            throw new Error('UserSettingsExtension has not been initialised.');
        }

        const collection = await this.app
            .getOrFail('restApi')
            .getResourceCollection('user-settings', { validateSchema: false });

        const raw: Record<string, Record<string, unknown>> = {};
        if (collection && collection.length > 0) {
            for (const item of collection as unknown as Array<Record<string, unknown>>) {
                if (!item || typeof item.id !== 'string') continue;
                const { id, _meta, _globalMeta, _links, ...attributes } = item;
                raw[id] = attributes as Record<string, unknown>;
            }
        }
        this.raw = raw;

        // Preserve the identity of already-returned namespace objects.
        for (const namespace of Object.keys(this.parsedCache) as (keyof HawkiUserSettingsSchemas)[]) {
            const schema = this.schemaRegistry.get(namespace as string);
            if (!schema) continue;

            const current = this.parsedCache[namespace];
            const next = schema.parse(this.raw[namespace as string] ?? {});
            if (isRecord(current) && isRecord(next)) {
                updateObject(current, next);
            } else {
                this.parsedCache[namespace] = next;
            }
        }
    }

    /**
     * Persists a partial update for a single public key within a namespace.
     *
     * Before sending the request, the **merged** result
     * (`{...currentValues[publicKey], ...partial}`) is validated against the
     * full namespace schema so the write is guaranteed to produce a valid state.
     * The PATCH is then sent as
     * `PATCH /api/hawki/v1/user-settings/{namespace}` with
     * `{data: {type: 'user-settings', id: namespace, attributes: {[publicKey]: partial}}}`.
     *
     * The response is merged back into the local raw data and parsed cache
     * using the same reactive identity-preservation trick as {@link refresh},
     * so components holding `useUserSettings()` observe the update without
     * a full refetch.
     *
     * The PATCH transport is deliberately isolated in this method so it can
     * later be swapped for a JSON:API Atomic Operations `POST` without
     * changing any call site.
     *
     * @param namespace  The settings namespace to write to (e.g. `'hawki-core'`).
     * @param publicKey  The settings-class public key within that namespace (e.g. `'core'`).
     * @param partial    The properties to update — only the changed keys.
     *
     * @throws ZodError if the merged result is invalid according to the namespace schema.
     * @throws Error if no schema is registered for the namespace (programming error).
     * @throws ApiTransportError if the PATCH request is rejected by the server.
     */
    public async save<N extends keyof HawkiUserSettingsSchemas>(
        namespace: N,
        publicKey: string,
        partial: Record<string, unknown>
    ): Promise<void> {
        if (!this.app) {
            throw new Error('UserSettingsExtension has not been initialised.');
        }

        const schema = this.schemaRegistry.get(namespace as string);
        if (!schema) {
            throw new Error(`No user-settings schema registered for namespace: ${String(namespace)}`);
        }

        const rawNs = this.raw?.[namespace as string] ?? {};
        const existing = (rawNs[publicKey] as Record<string, unknown>) ?? {};
        const merged = { ...existing, ...partial };

        // Validate: the full namespace resource must be valid after the merge.
        schema.parse({ ...rawNs, [publicKey]: merged });

        // PATCH transport — isolated so it can be swapped for
        // JSON:API Atomic Operations later without changing call sites.
        const response: Record<string, unknown> = await this.app
            .getOrFail('restApi')
            .updateResource('user-settings', namespace as string, { [publicKey]: partial });

        const { id: _id, ...serverAttributes } = response;
        this.raw = {
            ...this.raw,
            [namespace as string]: {
                ...(this.raw?.[namespace as string] ?? {}),
                [publicKey]: (serverAttributes as Record<string, unknown>)[publicKey] ?? merged
            }
        };

        // Update the parsed cache so already-returned objects stay live.
        if (namespace in this.parsedCache) {
            const current = this.parsedCache[namespace];
            const next = schema.parse(this.raw[namespace as string] ?? {});
            if (isRecord(current) && isRecord(next)) {
                updateObject(current, next);
            } else {
                this.parsedCache[namespace] = next;
            }
        }
    }

    /**
     * Returns a **debounced save closure** for one settings class — the
     * write-and-forget companion to {@link save} for high-frequency sources
     * that would otherwise fire a request per toggle (theme switches, slider
     * values, ...). Repeated calls within `debounceTime` reset the timer, so
     * only the **last** partial is persisted when the caller goes quiet —
     * earlier partials in the same window are intentionally dropped.
     *
     * Closures are cached per `namespace|publicKey|debounceTime`, so repeated
     * calls return the same instance (a fresh closure per call would never
     * debounce). The cache lives for the extension's lifetime — one per page.
     *
     * The closure is fire-and-forget (`void`): the underlying save's promise
     * is not observable. Pass `onError` to react to failures — e.g. an error
     * toast — instead of the default console error.
     *
     * @example
     * ```ts
     * const persistTheme = app.userSettings.getDebouncedSave('hawki-core', 'core', 500,
     *     (error) => useToastContext().error('Could not save the theme.'));
     * themeObserver.on('change', (theme) => persistPreference({theme}));
     * ```
     *
     * @param namespace    The settings namespace to write to (e.g. `'hawki-core'`).
     * @param publicKey    The settings-class public key within that namespace (e.g. `'core'`).
     * @param debounceTime Delay in ms since the last call before the save fires (default 500).
     * @param onError      Invoked when the eventual save fails; defaults to `console.error`.
     */
    public getDebouncedSave<N extends keyof HawkiUserSettingsSchemas>(
        namespace: N,
        publicKey: string,
        debounceTime = 500,
        onError?: (error: unknown) => void
    ): (partial: Record<string, unknown>) => void {
        const key = `${namespace as string}|${publicKey}|${debounceTime}`;

        const existing = this.debouncedSaves.get(key);
        if (existing) {
            return existing;
        }

        const save = debounce((partial: Record<string, unknown>) => {
            this.save(namespace, publicKey, partial).catch((error: unknown) => {
                if (onError) {
                    onError(error);
                    return;
                }
                console.error(`Failed to persist user settings "${namespace}.${publicKey}":`, error);
            });
        }, debounceTime);

        this.debouncedSaves.set(key, save);
        return save;
    }

    public async init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): Promise<void> {
        this.app = app;

        const registrar = createUserSettingsSchemaRegistrar(this.schemaRegistry);

        // App-owned namespaces register via the eager glob; plugins register
        // through the `settingSchemas()` lifecycle hook (see HawkiPlugin).
        registrar.addFromModules(import.meta.glob('$lib/app/schemas/user-settings/*.schema.{ts,js}', { eager: true }));
        await app.getOrFail('plugins').bootstrapper.runSettingSchemas(registrar);

        bootstrapper.onPreparationStage(() => this.refresh());
    }

    public provideProperties(): Record<string, unknown> {
        return { userSettings: this };
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}
