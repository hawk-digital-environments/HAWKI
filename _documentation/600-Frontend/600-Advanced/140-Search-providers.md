# Search providers

Modules contribute to the global quick finder through `HawkiModule.search()`. The kernel exposes the registered providers as `app.search`. Each SearchBar has its own query and selection state while sharing local indexes and source loading with other bars.

This is a frontend API. Providers that need server search use their own JSON:API resources through `app.restApi`. There is no global search endpoint. Chat conversation titles already load into ChatStore, so searching those titles requires no additional HTTP request.

## Register groups in a module

The plugin registers its module through its existing `modules()` hook. The module declares search groups synchronously, without reading stores or starting requests. The kernel supplies the application to provider callbacks after stores finish loading.

```ts
import type {HawkiModule} from '$lib/kernel/modules/types.js';
import type {ModuleSearchRegistrar} from '$lib/kernel/search/types.js';

export class ChatModule implements HawkiModule {
    readonly name = 'chat';

    search({group}: ModuleSearchRegistrar): void {
        group('actions', {
            kind: 'static',
            label: t => t('chat.module.title')
        }).add('actions', {
            items: ({app}) => [{
                id: 'new',
                entityKey: 'action/core:chat/new',
                title: app.translator.translate('chat.sidebar.newChat'),
                onSelect: () => {
                    app.stores.get('chat').startNew();
                    void app.router.goToRoute('chat.index');
                }
            }]
        });
    }
}
```

The kernel derives ownership from the registering plugin and module. For the core Chat module, the group and provider above each receive the ID `core:chat.actions`. Group and provider names have separate registries; each name must be unique within its registry for that module. An invalid declaration rolls back that module's search registration.

A group contains either static or dynamic providers. The registrar enforces this in TypeScript and at runtime. Register a separate dynamic group when adding server results alongside local results.

## Entry identity and fields

Providers return `SearchEntry` objects.

| Field | Purpose |
| --- | --- |
| `id` | Unique entry ID within the provider. |
| `entityKey` | Stable identity across providers, used for deduplication and recent selections. |
| `title` | Displayed title and primary searchable text. |
| `description` | Optional text displayed below the title. It does not participate in matching. |
| `icon` | Optional existing `IconComponent`. |
| `keywords` | Optional array of searchable terms. |
| `content` | Optional searchable full text or excerpt. |
| `onSelect()` | Current action to execute when selected. Use named routes for navigation. |

Use an entity identifier such as `ai-convs/<slug>`, never the title. For compound identities, include all identifying fields, for example `'entry/' + JSON.stringify([collectionId, entryId])`. Titles can change and need not be unique. Icons and action callbacks stay on the main thread, including for worker sources.

## Static sources

Static means matching uses local data. A source can download data once or receive background updates and still be static.

```ts
import type {StaticSource} from '$lib/kernel/search/types.js';

const conversationTitles: StaticSource = {
    items: ({app}) => app.stores.get('chat').conversations.map(row => ({
        id: row.slug,
        entityKey: 'ai-convs/' + row.slug,
        title: row.name,
        onSelect: () => void app.router.goToRoute(
            'chat.conversation', {slug: row.slug}
        )
    }))
};
```

`items({app, signal})` returns a synchronous array or a Svelte `Readable<readonly SearchEntry[]>`. The kernel observes reactive getters, including nested field changes, or subscribes to the readable. It updates the shared index when entries change. Plain arrays are fixed snapshots unless a reactive getter or store emits their changes.

Optional `load({app, signal})` runs once per activation, after observation starts. It fills the source's own reactive data and does not delay UI mounting. Opening another SearchBar does not reload it. Honor the signal in asynchronous work and check it before writing results into module state.

Optional `enabled({app, signal})` is reactive. Disabling removes the provider's entries and cancels its work; re-enabling starts a new activation. Locale changes refresh translated getters and headings. User or connection changes discard the old search context.

`matchIn` defaults to `'immediate'`. Set `matchIn: 'worker'` for a large text collection. Both modes share their indexes across SearchBars. Worker errors are retryable; the kernel does not silently move a large collection onto the main thread.

Chat registers its existing actions and conversation titles. Module-specific persistence and background indexing belong to the module; SearchBar only reports query work and provider failures.

## Dynamic sources and JSON:API

A dynamic source implements this callback instead of `items`:

```ts
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {SearchEntry} from '$lib/kernel/search/types.js';

type SearchCallback = (context: {
    app: HawkiApp;
    signal: AbortSignal;
    query: string;
    limit: number;
}) => Promise<readonly SearchEntry[]>;
```

Register it with `group(name, {kind: 'dynamic', label}).add(name, source)`. A dynamic source can also declare a reactive `enabled` callback.

Inside `search`, use `app.restApi.getResourceCollection(resourceType, {signal, query})` with a registered resource schema. Map the decoded rows to search entries and preserve the server's preferred order. The kernel keeps valid server results even when the returned title does not match the query, since the server may search fields it does not return.

Implement and test any missing filter on that provider's JSON:API resource before requesting it. For example, a server conversation-title adapter would need a `filter[q]` implementation on `ai-convs`; this change does not add that filter. Authorize records before applying matching and limits. Keep encrypted message-body matching local. Legacy controllers and endpoints must not be used by the new frontend.

Dynamic search starts at two normalized characters after a 250 ms debounce. The kernel permits four concurrent requests across the app and gives each running request a five-second deadline. `limit` requests a bounded candidate set; it is separate from the five visible rows allowed per group. Pagination and comprehensive search belong in module-specific views.

Each dynamic group publishes after all its providers settle. Successful siblings remain usable when a provider fails or times out. Retry operates on failed providers for the current query. Pass the supplied signal to the HTTP client; generation checks also prevent a late response from restoring stale results.

## Use SearchBar

```svelte
<script lang="ts">
    import SearchBar from '$lib/app/components/search/SearchBar.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';

    const app = useApp();
</script>

<SearchBar
    search={app.search}
    allowedScope={{moduleId: 'core:chat'}}
    showFilters={false}
    onSelect={hit => hit.onSelect()}
/>
```

Pass `active={open}` when the bar lives in a dialog. Closing disposes its session. The host's `onSelect` callback should close the dialog before calling `hit.onSelect()`. The shared SearchDialog already does this and owns the global keyboard shortcut.

`allowedScope` restricts a bar by `pluginId`, `moduleId`, or both. Optional visible filters only narrow that scope. Plugin and module filters combine with AND; selecting a module implies its plugin. Unknown or conflicting scopes yield no results or requests.

## Session API

Custom consumers can create a session directly:

```ts
const session = app.search.createSession({
    allowedScope: {moduleId: 'core:chat'}
});

session.setInput({query: 'design', scope: {moduleId: 'core:chat'}});
// Read session.state reactively in a Svelte component.
session.freezeOrder();

const hit = session.select(entityKey);
if (hit) hit.onSelect();

session.retry(providerId);
session.dispose();
```

`state.groups` contains group IDs, translated labels, kind, and rows. Rows have stable `entityKey` values and an `available` flag. `localPending` reports worker matching; `remotePending` reports dynamic query work. `providerErrors` identifies failed providers and their groups. `frozen` reports whether selection intent has fixed the displayed order.

Call `freezeOrder()` on keyboard selection or actual pointer movement into a result row. Call `select(entityKey)` before executing an action; it resolves the live entry and records eligible history centrally. Do not execute callbacks from a cached row after the source may have changed. Suspend dispatch during IME composition with `suspend()`, submit the completed input with `setInput()`, and resume with `suspend(false)`.

Dispose the session on close or unmount. Its cancellation does not stop another session or the shared static sources.

## Ranking, freezing, and recents

Fuse.js scores local and server results using title, keyword, and content weights of 2, 1.5, and 1. All search paths use the same incremental engine, including the worker. [Token search](https://www.fusejs.io/token-search.html) requires every query term to match somewhere across those fields, with typo tolerance, substring matching, and case and accent insensitivity. The fuzzy threshold is 0.3. The kernel inverts Fuse's lower-is-better scores for descending ranking, reserving zero for unmatched server results. Server results without a local text match remain eligible after scored results. Ties retain provider and item order. A current static copy wins when multiple providers return the same entity. Groups follow their best match, with at most five rows per group and 20 rows overall.

Once selection intent freezes the view, existing rows keep their positions. New groups append within the limits; new matches for visible groups wait for the next query or scope change. A removed entry leaves a disabled placeholder, and its action becomes unavailable immediately. Retry preserves the freeze.

An empty query shows up to ten distinct recent static selections, resolved against current enabled entries and filtered to the scope. Storage contains only entity IDs and timestamps, scoped to the user and connection. Dynamic selections never enter this history. Recents retain the five-per-group cap and do not fill spare slots with unrelated entries. If no recent entries resolve, the bar falls back to static group, provider, and item registration order. Empty queries never request server results.

## Source files

The provider contract is in `resources/js/kernel/search/types.ts`. Registration, observation, and sessions live in `resources/js/kernel/search/`. Chat's provider definitions are in `resources/js/plugins/core/modules/chat/search.ts`. The reusable bar and dialog live in `resources/js/app/components/search/`.

## Testing

Run `bin/env npm run test:search` for provider lifecycle, sessions, ranking, worker transport and cancellation tests. Run `bin/env npm run check` for frontend type and Svelte checks.
