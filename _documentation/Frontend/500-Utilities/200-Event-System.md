# Event System

HAWKI does not use a general-purpose event bus. Instead it provides three purpose-built dispatcher classes — `SyncPipeline`, `AsyncPipeline`, and `ParallelAsyncWorkflow` — each enforcing a different execution contract at the TypeScript level. The type system prevents misuse: you cannot accidentally register an async handler on a `SyncPipeline`, and you cannot mix up sequential and concurrent execution without explicitly choosing the right type.

All three share the same basic API shape:

- `on(type, handler)` — registers a handler and returns an idempotent unsubscribe function.
- `trigger(type, data?)` — fires all registered handlers for that type.

Source files: `resources/js/utils/flows/`

---

## Choosing the Right Type

| Situation                                                       | Use                     |
|-----------------------------------------------------------------|-------------------------|
| Handlers must complete synchronously — no Promises allowed      | `SyncPipeline`          |
| Handlers are async and must run one after another               | `AsyncPipeline`         |
| Handlers are async and can run concurrently (up to N at a time) | `ParallelAsyncWorkflow` |

---

## `SyncPipeline`

`SyncPipeline` is the right choice when handlers must not defer work. The `SyncHandler` type is defined as `(data: TData) => void` — it explicitly excludes `Promise`-returning functions. Registering an `async` handler is a TypeScript error, which eliminates the silent bug where async work is kicked off but never awaited.

### API

**`on(type, handler)`**

Registers a synchronous handler for `type`. Handlers are called in registration order. Returns an unsubscribe function; calling it more than once is safe.

**`trigger(type, data?)`**

Calls all registered handlers for `type` in order and returns the data that was passed in, making it convenient when the caller wants a reference to the data after handlers have processed it. When the payload type is `void`, `data` is omitted.

**`triggerVoid(type, data?)`**

Same as `trigger` but returns `void`. Use this at call sites where a `void`-typed return is expected and the payload does not need to be inspected afterwards.

### Typed example

```typescript
import {SyncPipeline} from '$lib/utils/flows/SyncPipeline.js';

interface Events {
    userLoggedIn: { userId: string };
    userLoggedOut: void;
}

const pipeline = new SyncPipeline<Events>();

const off = pipeline.on('userLoggedIn', ({userId}) => {
    console.log('logged in:', userId);
});

pipeline.trigger('userLoggedIn', {userId: '42'});
pipeline.triggerVoid('userLoggedOut');

off(); // unregister
```

---

## `AsyncPipeline`

`AsyncPipeline` accepts handlers typed as `(data: TData) => Promise<void> | void`. Both async and synchronous handlers are valid. When `trigger` is called it `await`s each handler in registration order before starting the next one, giving sequential, predictable execution. `trigger` itself returns a `Promise` that resolves once all handlers have completed.

### API

**`on(type, handler)`**

Registers a handler (sync or async) for `type`. Returns an unsubscribe function.

**`trigger(type, data?)`**

Awaits each handler for `type` in registration order, then resolves to the data that was passed in.

**`triggerVoid(type, data?)`**

Same as `trigger` but resolves to `void`. Use when a `Promise<void>` return type is required at the call site and the payload is not needed afterwards.

### Typed example

```typescript
import {AsyncPipeline} from '$lib/utils/flows/AsyncPipeline.js';

interface Events {
    beforeSave: { data: FormData };
    afterSave: void;
}

const pipeline = new AsyncPipeline<Events>();

const off = pipeline.on('beforeSave', async ({data}) => {
    await validate(data);
});

await pipeline.trigger('beforeSave', {data: formData});
await pipeline.triggerVoid('afterSave');

off(); // unregister
```

---

## `ParallelAsyncWorkflow`

`ParallelAsyncWorkflow` extends `AsyncPipeline` and overrides `trigger` with a sliding concurrency pool. The constructor accepts a `chunkSize` argument (default `5`) that caps how many handlers may run at the same time.

### How the sliding window works

Unlike fixed batching — where you wait for a group of N to finish before starting the next group — `ParallelAsyncWorkflow` keeps exactly `chunkSize` handlers in flight at all times. As soon as any running handler resolves, the next pending handler enters the pool immediately. If there are four handlers and `chunkSize` is 3, handler 4 starts as soon as the first of handlers 1–3 finishes, rather than waiting for all three to complete. `trigger` resolves once every handler has finished.

This makes `ParallelAsyncWorkflow` well suited for independent work within a single stage — parallel boot tasks, concurrent data fetches — where individual tasks have variable durations and fixed batching would leave concurrency slots idle.

### Typed example

```typescript
import {ParallelAsyncWorkflow} from '$lib/utils/flows/ParallelAsyncWorkflow.js';

interface Stages {
    boot: { app: App };
}

// Run up to 3 boot tasks concurrently.
const workflow = new ParallelAsyncWorkflow<Stages>(3);

workflow.on('boot', async ({app}) => loadConfig(app));
workflow.on('boot', async ({app}) => loadSession(app));
workflow.on('boot', async ({app}) => prefetchRoutes(app));
// Starts as soon as any of the above finishes, not after all three.
workflow.on('boot', async ({app}) => warmCache(app));

await workflow.trigger('boot', {app});
```
