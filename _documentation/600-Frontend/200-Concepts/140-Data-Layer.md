# Data Layer

The data layer is the surface between a Svelte component and the HAWKI REST API: runtime configuration, connection metadata, typed fetch helpers, and resource schema validation. All of it is assembled from kernel extensions and reached through the hooks in `app/hooks/`. See [The App & Kernel](120-App-and-Kernel/index.md) for the extension overview.

## Config vs Connection

Two separate subsystems carry data that components need before they can do anything useful.

**Config** (`ConfigurationExtension`, exposed as `app.config`) holds the frontend runtime configuration blob. It is fetched from the API once during the `preparation` boot stage and cached for the lifetime of the page. The blob is divided into namespaces — `'hawki-core'` is the default and contains locale settings, file storage limits, allowed MIME types, WebSocket connection details, AI display settings, and cryptographic salts. Each namespace is parsed against its registered Zod schema lazily on the first read, and the parsed result is cached.

**Connection** (`ClientExtension`, exposed as `app.connection`) holds authentication state and route metadata. The raw connection object is fetched once during the `preparation` stage. The connection family of hooks narrows the union type down to the variant relevant in context.

## `useConfig()`

```svelte
<script lang="ts">
    import {useConfig} from '$lib/app/hooks/useConfig.svelte.js';

    // Default namespace — returns z.infer<HawkiConfigSchemas['hawki-core']>
    const coreConfig = useConfig();

    // Named namespace
    const myFeature = useConfig('my-feature');
</script>

<p>Default locale: {coreConfig.locale.default}</p>
```

Called without arguments, `useConfig()` returns the `'hawki-core'` slice. The return type is fully inferred from `HawkiConfigSchemas` — TypeScript knows the shape of every registered namespace at compile time. The result is wrapped in `$derived.by(...)`, so it tracks as a reactive value. Calling it with an unregistered namespace key throws at runtime, which is always a programming error rather than a runtime condition.

Config is safe to read once the `preparation` boot stage has completed.

## Connection Family

All hooks read from the same in-memory connection object loaded during `preparation`. They differ in which connection variant they accept — narrowing hooks return `null` (rather than throwing) when the connection is not in the expected state, so templates can simply check for `null`.

| Hook | When to use |
|---|---|
| `useConnection()` | Any code that only needs the API version or locale, regardless of auth state. Returns the full `Connection` union type. |
| `useAuthenticatedConnection()` | Code that requires an active session. Returns the `internal_authenticated` connection (with `userinfo`), or `null` if not authenticated. |
| `useConnectionWithUserInfo()` | Code that needs user info but must work for both authenticated and registering users. Returns the connection or `null` otherwise. |

The `Connection` is a discriminated union on its `type` field: `'internal'` (anonymous), `'internal_authenticated'` (logged in, includes `userinfo`), or `'internal_registering_user'` (mid-registration, includes partial `userinfo`). Narrow on `type` yourself if you need to branch, or use the narrowing hooks above.

Use the narrowest hook that fits the context. Using `useConnection()` everywhere suppresses type errors that would otherwise catch a component being rendered in the wrong auth state.

## Fetching API Resources

All fetch helpers live on `app.restApi` (`RestApi` in `kernel/api/RestApi.ts`), reached via the `useRestApi()` hook (in `app/hooks/useApi.ts`). They communicate with the JSON:API endpoint, set the required `Accept` and `Content-Type` headers automatically, decode the JSON:API envelope, and parse JSON:API error responses into readable messages before throwing.

```svelte
<script lang="ts">
    import {useRestApi} from '$lib/app/hooks/useApi.js';
    const restApi = useRestApi();
</script>
```

### `fetch`

The lowest-level primitive. It sends the request, applies optional `beforeSchema`/`schema`/`afterSchema` transforms, and returns the result. All other helpers delegate to it. Use it directly only when none of the typed helpers fit.

### `getResourceCollection`

```ts
restApi.getResourceCollection<R extends keyof HawkiResourceSchemas>(
    resourceType: R,
    options?: GetResourceCollectionOptions
): Promise<JsonApiCollection<HawkiResourceSchemas[R]>>
```

Fetches `GET /{resourceType}`, decodes the JSON:API index response, and validates the result array against the registered Zod schema for that resource type. Pagination and filter parameters can be passed via `options.query`.

```ts
// Typed + validated — schema for 'ai-models' must be registered
const collection = await restApi.getResourceCollection('ai-models');
collection.list.forEach(model => console.log(model.label));

// Skip validation for a one-off request with no registered schema
const raw = await restApi.getResourceCollection('some-resource', {validateSchema: false});
```

Pass a string that is not a key of `HawkiResourceSchemas` and the return type falls back to `JsonApiCollection<any[]>` with no validation.

### `getResource`

```ts
restApi.getResource<R extends keyof HawkiResourceSchemas>(
    resourceType: R,
    id: string | number,
    options?: GetResourceOptions
): Promise<HawkiResourceSchemas[R]>
```

Fetches `GET /{resourceType}/{id}`, decodes the single-resource JSON:API response, and validates it. Works identically to `getResourceCollection` but returns one object instead of an array.

### `getFromResourceAction` / `postToResourceAction`

For RPC-style endpoints that do not follow the standard CRUD pattern — i.e. `/{resourceType}/{action}`:

```ts
// GET /{resourceType}/{action}
restApi.getFromResourceAction(resourceType, action, options?: { schema?: ZodTypeAny })

// POST /{resourceType}/{action}
restApi.postToResourceAction(resourceType, action, data, options?: { schema?: ZodTypeAny })
```

Unlike the `getResource*` helpers, these do not apply JSON:API decoding — the raw response is returned as-is. Pass `options.schema` with a Zod schema to validate the response and get a narrowed return type. Omit it for fire-and-forget calls where the response shape does not matter.

```ts
const result = await restApi.getFromResourceAction('reports', 'generate', {
    schema: MyReportSchema
});
// result is z.infer<typeof MyReportSchema>
```

## Resource Schema Registry

`HawkiResourceSchemas` in `kernel/extendableTypes.ts` is an empty interface that grows at compile time via TypeScript declaration merging. Each resource schema file augments it with one entry. `RestApi` uses the registry to infer return types and to look up the Zod schema for runtime validation — no explicit type assertion or schema lookup is needed at the call site.

The registry itself is `app.resourceSchemas` (`ResourceSchemaExtension`). It eager-globs `resources/js/app/schemas/resources/*.schema.{ts,js}` on init to register the core schemas, and plugins register their own via the `resourceSchemas()` lifecycle hook (the core plugin's live under `plugins/core/schemas/resources/`). You do not need to import individual schema files or register them manually.

### Adding a new resource schema

Create a file named `{resource-type}.schema.ts` either in `resources/js/app/schemas/resources/` (for app-wide resources) or in your plugin's `schemas/resources/` directory. The filename stem becomes the resource type key.

```ts
// resources/js/app/schemas/resources/my-thing.schema.ts
import z from 'zod';

const MyThingSchema = z.object({
    id: z.string(),
    name: z.string(),
    // ...
});

export default MyThingSchema;

export type MyThing = z.infer<typeof MyThingSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'my-thing': MyThing;
    }
}
```

Once the file exists, `restApi.getResourceCollection('my-thing')` returns `JsonApiCollection<MyThing>` and the response is validated automatically. No additional wiring is required.

The same auto-registration and declaration-merging pattern applies to config namespaces in `resources/js/app/schemas/config/` (or a plugin's `schemas/config/`), augmenting `HawkiConfigSchemas` instead.

## Keychain

The user's encryption keys are exposed reactively through the `keychain` store (`useStore('keychain')`, see [Stores](130-Stores.md#keychainstore)) — `publicKey`, `privateKey`, `aiConvKey`, `roomKeys`, plus a `waitingToLoad` promise that resolves once the initial load completes. All key material is stored on the server in encrypted form and decrypted in-browser on load.

For the cryptographic primitives that underpin the keychain (symmetric encryption, asymmetric encryption, key derivation), see [Encryption](170-Encryption.md). The lower-level handle used by the store and migrations lives in `kernel/keychain/keychainHandle.ts`.
