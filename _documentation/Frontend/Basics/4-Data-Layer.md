# Data Layer

The data layer sits under `resources/js/data/` and covers everything between a Svelte component and the HAWKI REST API: runtime configuration, connection metadata, typed fetch helpers, resource schema validation, and the encryption keychain. All of these are available after the `preparation` boot stage completes.

## Config vs Connection

Two separate subsystems carry data that components need before they can do anything useful.

**Config** (`data/config/config.ts`) holds the frontend runtime configuration blob. It is fetched from the API once during startup via `loadConfig()` and cached for the lifetime of the page. The blob is divided into namespaces — `'hawki-core'` is the default and contains locale settings, file storage limits, allowed MIME types, WebSocket connection details, AI display settings, and cryptographic salts. Each namespace is parsed lazily on the first `getConfig()` call for that namespace, and the result is cached.

**Connection** (`data/connection/connection.ts`) holds authentication state and route metadata. The raw connection object is fetched once via `loadConnection()`. The connection family of functions narrows the union type down to the connection variant that is relevant in context — guest, registering, or fully authenticated.

## `getConfig()`

```ts
import { getConfig } from '$lib/data/config/config.js';

// Default namespace — returns z.infer<ConfigSchemaRegistry['hawki-core']>
const { locale, storage_files, security } = getConfig();

// Named namespace
const myFeature = getConfig('my-feature');
```

Called without arguments, `getConfig()` returns the `'hawki-core'` slice. The return type is fully inferred from `ConfigSchemaRegistry` — TypeScript knows the shape of every registered namespace at compile time. Calling it with an unregistered namespace key throws at runtime, which is always a programming error rather than a runtime condition.

The config data comes from the `configs/public` API endpoint. `getConfig()` is safe to call once the `preparation` boot stage has completed.

## Connection Family

All four functions read from the same in-memory connection object loaded by `loadConnection()`. They differ only in which connection variant they accept — narrowing to anything other than the expected type throws immediately with a descriptive error.

| Function | When to use |
|---|---|
| `getConnection()` | Any code that only needs the API version or locale, regardless of auth state. Returns the full `Connection` union type. |
| `getAuthenticatedConnection()` | Code that requires an active session — throws if the connection type is not `internal_authenticated`. Returns `InternalAuthenticatedConnection`, which includes `userinfo`. |
| `getRegisteringUserConnection()` | Code that runs during the registration flow — throws if the connection type is not `internal_registering_user`. Returns `InternalRegisteringUserConnection`. |
| `getConnectionWithUserInfo()` | Code that needs user info but must work for both authenticated and registering users. Throws for unauthenticated (guest) connections. |

Use the narrowest function that fits the context. Using `getConnection()` everywhere suppresses type errors that would otherwise catch a component being rendered in the wrong auth state.

## Fetching API Resources

All fetch helpers live in `data/api/api.ts` and communicate with the JSON:API endpoint at `/api/hawki/v1/`. They set the required `Accept` and `Content-Type` headers automatically and parse JSON:API error responses into readable messages before throwing.

### `fetchApi`

```ts
fetchApi<T>(path: string, options: FetchResourceApiOptions): Promise<T>
```

The lowest-level primitive. It sends the request, checks `response.ok`, and returns `response.json()`. All other helpers delegate to this one. Use it directly only when none of the typed helpers fit — for example, when POSTing to a fully custom endpoint that returns neither a JSON:API resource nor a collection.

### `getResourceCollectionFromApi`

```ts
getResourceCollectionFromApi<R extends keyof ResourceSchemaRegistry>(
    resourceType: R,
    options?: FetchResourceCollectionOptions
): Promise<JsonApiCollection<ResourceSchemaRegistry[R]>>
```

Fetches `GET /{resourceType}`, decodes the JSON:API index response, and validates the result array against the registered Zod schema for that resource type. Pagination and filter parameters can be passed via `options.query`.

```ts
import { getResourceCollectionFromApi } from '$lib/data/api/api.js';

// Typed + validated — schema for 'ai-models' must be registered
const collection = await getResourceCollectionFromApi('ai-models');
collection.list.forEach(model => console.log(model.label));

// Skip validation for a one-off request with no registered schema
const raw = await getResourceCollectionFromApi('some-resource', { validateSchema: false });
```

Pass a string that is not a key of `ResourceSchemaRegistry` and the return type falls back to `JsonApiCollection<any[]>` with no validation.

### `getResourceFromApi`

```ts
getResourceFromApi<R extends keyof ResourceSchemaRegistry>(
    resourceType: R,
    id: string | number,
    options?: FetchResourceOptions
): Promise<ResourceSchemaRegistry[R]>
```

Fetches `GET /{resourceType}/{id}`, decodes the single-resource JSON:API response, and validates it. Works identically to `getResourceCollectionFromApi` but returns one object instead of an array.

### `getFromResourceAction` / `postToResourceAction`

For RPC-style endpoints that do not follow the standard CRUD pattern — i.e. `/{resourceType}/{action}`:

```ts
// GET /{resourceType}/{action}
getFromResourceAction(resourceType, action, options?: { schema?: ZodTypeAny })

// POST /{resourceType}/{action}
postToResourceAction(resourceType, action, data, options?: { schema?: ZodTypeAny })
```

Unlike the `getResource*` helpers, these do not apply JSON:API decoding — the raw response is returned as-is. Pass `options.schema` with a Zod schema to validate the response and get a narrowed return type. Omit it for fire-and-forget calls where the response shape does not matter.

```ts
const result = await getFromResourceAction('reports', 'generate', {
    schema: MyReportSchema
});
// result is z.infer<typeof MyReportSchema>
```

## Resource Schema Registry

`ResourceSchemaRegistry` in `data/resources/resourceRegistry.ts` is an empty interface that grows at compile time via TypeScript declaration merging. Each resource schema file augments it with one entry. The API helpers use the registry to infer return types and to look up the Zod schema for runtime validation — no explicit type assertion or schema lookup is needed at the call site.

`autoRegisterResourceSchemas()` globs all files matching `resources/js/schemas/resources/*.schema.{ts,js}` at startup and populates the internal runtime registry automatically. You do not need to import individual schema files or register them manually.

### Adding a new resource schema

Create a file in `resources/js/schemas/resources/` named `{resource-type}.schema.ts`. The filename stem becomes the resource type key.

```ts
// resources/js/schemas/resources/my-thing.schema.ts
import z from 'zod';

const MyThingSchema = z.object({
    id: z.string(),
    name: z.string(),
    // ...
});

export default MyThingSchema;

export type MyThing = z.infer<typeof MyThingSchema>;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'my-thing': MyThing;
    }
}
```

Once the file exists, `getResourceCollectionFromApi('my-thing')` returns `JsonApiCollection<MyThing>` and the response is validated automatically. No additional wiring is required.

The same auto-registration and declaration-merging pattern applies to config namespaces in `resources/js/schemas/config/`, augmenting `ConfigSchemaRegistry` in `data/config/config.ts` instead.

## Keychain

`createKeychainHandle()` from `data/keychain/keychainHandle.ts` returns an object that manages the user's encryption keys for end-to-end encryption. All key material is stored on the server in encrypted form (symmetrically encrypted with a key derived from the user's passkey) and decrypted in-browser on load. Contributors should use the keychain handle for all key operations and avoid calling the underlying crypto primitives directly.

`KeychainHandle` exposes the following public methods:

| Method | Purpose |
|---|---|
| `load()` | Fetches all keychain values from the API and decrypts them into memory. Call this once after the user authenticates. |
| `onChange(callback)` | Registers a callback that fires whenever the keychain state changes (after a load or update). |
| `validateKeychainPassword(passkey)` | Checks whether a passkey string is correct by attempting to decrypt the stored validator. Returns `true` or `false`. |
| `initializeNewKeychain()` | Generates a fresh RSA key pair and an AI conversation key, then stores them in the keychain. Used during initial account setup. |
| `publicKey()` | Returns the user's RSA public key (`CryptoKey`). |
| `privateKey()` | Returns the user's RSA private key (`CryptoKey`). |
| `aiConvKey()` | Returns the symmetric key used for encrypting AI conversations. |
| `roomKeys()` | Returns a map of all loaded room keys indexed by room slug. Each entry is a `RoomKeys` object containing `roomKey`, `aiKey`, and `aiLegacyKey`. |
| `roomKeysOf(roomSlug)` | Returns the `RoomKeys` for a single room, or `null` if none are loaded. |
| `createRoomKeys(roomSlug)` | Generates a new symmetric room key and derives the associated AI keys, then persists them to the keychain. Returns the new `RoomKeys`, or `null` if keys for that room already exist. |
| `importRoomKey(roomSlug, roomKey)` | Imports an existing room key (received via an invitation) into the keychain, overwriting any existing entry. |
| `removeRoomKeys(roomSlug)` | Removes all keys for a room from the keychain. |
| `listKeys(type?)` | Returns all key names in the keychain, optionally filtered to a specific key type. |
| `doUpdate(updater)` | Runs a `BatchKeychainUpdater` and persists the changes to the API. For internal use and migrations. |
| `doUpdatesDeferred(runner)` | Collects multiple batch updates into a single API call. For migrations only — intermediate results inside the runner are `null`. |
| `brokenRoomKeys()` | Returns room keys that are missing their AI counterpart keys. Used in migration tooling. |

For the cryptographic primitives that underpin the keychain (symmetric encryption, asymmetric encryption, key derivation), see **Advanced → Encryption**.
