# Writing a Frontend Extension

An **extension** is a self-contained subsystem that plugs into the `HawkiApp` during startup. This page is for the rare case where you need a *new app-wide subsystem* — a new registry, a new cross-cutting service that other extensions or plugins depend on. Most features do **not** need a new extension; they go in a plugin instead (see [Writing a Frontend Plugin](100-Writing-a-Frontend-Plugin.md)).

:::tip[Extension or plugin?]
Add an extension only when the surface must live on `app.*` and be available to other extensions/plugins during assembly — e.g. a new registry that plugins contribute to, or a service that `init()` of later extensions depends on. If your feature is stores, schemas, modules, routes, or migrations, write a plugin. If it is a one-off service used by components, a store is usually enough.
:::

The contract lives in `resources/js/kernel/HawkiApp.ts`. Source examples to mirror: `kernel/stores/StoreExtension.ts`, `kernel/localization/LocalizationExtension.svelte.ts`, `kernel/shell/ShellExtension.svelte.ts`.

---

## The contract

```ts
export type HawkiAppExtension = {
    /** Property descriptors merged onto the app object. Called once, right after init(). */
    provideProperties(): Record<string, any>;
    /** Runs while the app is still being assembled; may register bootstrapper hooks or further extensions. */
    init?(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): void | Promise<void>;
    /** Runs once every extension has been added and the app is fully assembled. */
    ready?(app: HawkiApp, bootstrapper: Bootstrapper): void | Promise<void>;
};
```

- `init(app, bootstrapper)` — runs in array order (the order extensions are passed to `createApp()`). The app is still `UnfinishedHawkiApp`: later extensions are not yet available. Reach extensions registered *before* you with `app.getOrFail('name')` (throws if missing). Register boot-stage work here via `bootstrapper.onStage…`.
- `ready(app, bootstrapper)` — runs after every extension has been added, so the full `app` surface is available. Use it to wire cross-extension behaviour that needs the complete app.
- `provideProperties()` — returns an object whose keys become real properties on `app` (via `Object.defineProperties`). Use getters so the property always resolves to the live extension instance. This is called right after `init()`, before `ready()`.

Both `init` and `ready` are optional. An extension that only contributes a static registry can omit them.

:::warning[No `bootstrapper` singleton]
`Bootstrapper.ts` exports only the class. Always obtain the instance from the `bootstrapper` parameter your hook receives — do not construct a second one or import a singleton. See [Concepts → App Startup](../../600-Frontend/200-Concepts/120-App-and-Kernel/110-App-Startup.md).
:::

---

## Declaration merging: making `app.yourName` typed

`provideProperties()` makes the property exist at runtime; declaration merging makes TypeScript know about it. Augment `HawkiAppExtensions` in `kernel/extendableTypes.ts` next to your class:

```ts
import type {HawkiAppExtension, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {HawkiAppExtensions} from '$lib/kernel/extendableTypes.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        myFeature: WithoutAppExtensionInternals<MyFeatureExtension>;
    }
}
```

`WithoutAppExtensionInternals<MyFeatureExtension>` strips `init`/`ready`/`provideProperties` so consumers get only your public API, not the lifecycle plumbing. Forgetting this (exporting the raw class) leaks lifecycle methods onto `app.myFeature`.

---

## A complete, minimal extension

This template mirrors the real conventions. It exposes one property on `app`, reads a dependency registered earlier, and schedules work on a boot stage.

```ts
// resources/js/kernel/myFeature/MyFeatureExtension.ts
import type {HawkiApp, HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        myFeature: WithoutAppExtensionInternals<MyFeatureExtension>;
    }
}

export class MyFeatureExtension implements HawkiAppExtension {
    private things = new Map<string, unknown>();

    /** Public registry method consumers call via `app.myFeature.register(...)`. */
    public register(name: string, thing: unknown): void {
        this.things.set(name, thing);
    }

    public has(name: string): boolean {
        return this.things.has(name);
    }

    public get(name: string): unknown {
        const thing = this.things.get(name);
        if (!thing) {
            throw new Error(`'${name}' is not registered on myFeature.`);
        }
        return thing;
    }

    public async init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): Promise<void> {
        // Reach a dependency that is registered BEFORE this extension in app.ts.
        const restApi = app.getOrFail('restApi');

        // Schedule work on a boot stage — runs concurrently with other 'main' handlers.
        bootstrapper.onMainStage(async () => {
            // …use restApi to load something into the registry…
        });
    }

    public provideProperties(): Record<string, any> {
        const extension = this;
        return {
            get myFeature() {
                return extension;
            },
        };
    }
}
```

Then add it to the ordered list in `app.ts`:

```ts
import {MyFeatureExtension} from '$lib/kernel/myFeature/MyFeatureExtension.js';
// …
new MyFeatureExtension(),   // place after any extension it depends on
```

After that, `app.myFeature.register(...)` is typed and available in any component via `useApp().myFeature` (or a dedicated hook you add under `app/hooks/`).

---

## Letting plugins contribute to your extension

If your extension owns a registry that plugins should populate (the common case — stores, resource schemas, modules all work this way), expose a `registrar` and let `PluginBootstrapper` drive a plugin lifecycle hook. The shape, from `StoreExtension`:

```ts
public async init(app: UnfinishedHawkiApp) {
    const registrar = createStoreRegistrar(this.stores);   // your registrar
    await app.getOrFail('plugins').bootstrapper.runStores(registrar);
}
```

`runStores` calls `plugin.stores(registrar, ctx)` on every registered plugin. You add a matching `run*` method on `PluginBootstrapper` and a lifecycle hook on `HawkiPlugin` (see `kernel/plugins/types.ts`). This keeps plugins decoupled from your extension's internals — they only see the registrar.

:::warning[Don't reach for plugins inside provideProperties]
`provideProperties()` runs before `ready()` and, for extensions registered after `PluginExtension`, before plugin `boot()` has run. Only touch plugins from `init()`/`ready()` (via `app.getOrFail('plugins')`), never from `provideProperties()`.
:::

---

## Where to go next

| I want to… | Read |
|---|---|
| Add stores, schemas, modules, routes, or migrations | [Writing a Frontend Plugin](100-Writing-a-Frontend-Plugin.md) |
| Understand assembly order and the boot stages | [Concepts → The App & Kernel](../../600-Frontend/200-Concepts/120-App-and-Kernel/index.md) and [Concepts → App Startup](../../600-Frontend/200-Concepts/120-App-and-Kernel/110-App-Startup.md) |
| See a real registry extension | `kernel/stores/StoreExtension.ts`, `kernel/resources/ResourceSchemaExtension.ts` |
| See a real stage-hooking extension | `kernel/localization/LocalizationExtension.svelte.ts`, `kernel/shell/ShellExtension.svelte.ts` |
