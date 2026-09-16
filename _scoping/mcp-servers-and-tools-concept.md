# Concept: MCP servers as a tree table with their tools as sub-rows

Third iteration. Rounds 1 (server column + filter on a separate tools page)
and 2 (two stacked tables on one Workspace page) are committed. This round
replaces the two tables with **one tree table**: every MCP server is a parent
row that expands, like a Section in the admin sidebar, to reveal its tools as
indented sub-rows in the same grid. Built-in function tools (no server) hang
under one synthetic parent row "Built-in (HAWKI)".

```
MCP servers and tools                                     [Actions ▾] [Create]
<description>
[search servers]
┌──────────────────────────────────────────────────────────────────────────────┐
│ Name                    │ Type     │ Status  │ Details             │ Access  │ ⋯ │
├──────────────────────────────────────────────────────────────────────────────┤
│ ▸ Demo MCP        (3)   │ HTTP     │ Online  │ https://…/mcp       │ API key set │ ⋯ │
│ ▾ Weather server  (2)   │ SSE      │ Offline │ https://…/sse       │ API key not set │ ⋯ │
│     forecast            │ MCP      │ [on]    │ web_search          │ Users with web search │ ⋯ │
│     current_conditions  │ MCP      │ [off]   │ —                   │ Unavailable to users │ ⋯ │
│ ▾ Built-in (HAWKI) (5)  │ —        │ —       │ —                   │ —       │   │
│     Web search          │ Built-in │ [on]    │ web_search          │ Users with web search │ ⋯ │
│     …                                                                          │
└──────────────────────────────────────────────────────────────────────────────┘
Page 1 of 1 · 2 entries                                  Rows per page 25 ‹ ›
```

## Vocabulary

Per `CONTEXT.md`: this is the **Workspace** `mcp` in the **Section** *AI
services*; it holds two **Record Sets** (servers, tools). Use these words in
comments and docs.

## Data model on the page

Two Record Sets stay (they own reads, writes, editors, dialogs, versions):

- `servers = useAdminRecordSet(...)` over `admin-mcp`, paginated and sorted by
  the server as today (search, `kind` filter, row actions test/discover,
  create/edit/delete). **This Record Set drives the table state** (pagination,
  sorting, column filters bound to `DataTable`).
- `tools = useAdminRecordSet(...)` over `admin-tools` with
  `pagination = { pageIndex: 0, pageSize: 100 }`, no search, no filters. Its
  rows are grouped client-side by `mcp_server_id`. If `tools.total` exceeds
  `tools.rows.length`, `AdminPage` shows the `hint`
  `admin.tools_truncated` (":shown of :total tools are shown"). Its
  `refresh` stays `() => app.refreshConnection()`; the servers Record Set's
  `refresh` additionally awaits `tools.load()` (discover/delete change tools).
- `AdminPage` receives `related={[{ recordSet: tools, editor: 'tools', title: __('admin.tools') }]}`
  as today, so the tool editor, confirmations and lifecycle keep working.

### Tree rows (`resources/js/plugins/admin/mcpTree.ts`, pure TS, unit-tested)

```ts
export type McpTreeRow =
    | { id: string; kind: 'server'; server: AdminMcpServerResource; subRows: McpTreeRow[] }
    | { id: 'builtin'; kind: 'builtin'; subRows: McpTreeRow[] }
    | { id: string; kind: 'tool'; tool: AdminToolResource };

/**
 * Parent rows for the current server page plus, on the last page only, the
 * synthetic "Built-in (HAWKI)" parent when function tools exist. Tool row ids
 * are prefixed `tool:` so they never collide with server ids.
 */
export function buildMcpTree(
    servers: AdminMcpServerResource[],
    tools: AdminToolResource[],
    options: { lastPage: boolean }
): McpTreeRow[];
```

Rules: a server's `subRows` are the tools with `mcp_server_id === Number(server.id)`,
in the order the API returned them; the built-in parent collects tools with
`mcp_server_id === null`; it is appended after the servers and only when
`options.lastPage` is true and at least one such tool exists. Servers with no
tools still get an (empty) `subRows` array, so they show a disabled chevron
and the count badge "0".

## Shared component change: `DataTable.svelte` learns sub-rows

`resources/js/components/ui/data-table/types.ts`: add `rowExpandingFeature` and
`expandedRowModel: createExpandedRowModel()` to `dataTableFeatures`; re-export
`ExpandedState`.

`DataTable.svelte`, new optional props (all backwards compatible; `AdminTable`
is the only consumer today):

```ts
/** Returns the child rows of a row; presence turns the table into a tree. */
getSubRows?: (row: T) => T[] | undefined;
/** Bindable tanstack expanded state, keyed by row id. */
expanded?: ExpandedState;               // $bindable({})
/** aria-label of a row's expand toggle for the given state. */
expandLabel?: (row: T, expanded: boolean) => string;
```

Table options: `getSubRows` passed through (wrapped so `undefined` stays
`undefined`), `paginateExpandedRows: false`, `state.expanded` getter,
`onExpandedChange` updating `expanded`. Do **not** call `onChange` on
expansion; expanding is client-side.

Rendering:

- On every `<tr>` set `style="--depth: {row.depth}"` and `class:sub-row={row.depth > 0}`.
- In the **first data cell** of each row (before its content) render the
  toggle: if `getSubRows` is set and `row.depth === 0`, a `Button`
  (`variant="ghost"`, `size="sm"`, `iconLeft={ChevronRightIcon}`,
  `aria-expanded={row.getIsExpanded()}`,
  `aria-label={expandLabel?.(row.original, row.getIsExpanded()) ?? ''}`,
  `disabled={!row.getCanExpand()}`, `onclick={row.getToggleExpandedHandler()}`)
  whose icon rotates 90° when expanded (`class:open`, transition uses
  `motionDuration` like `SidebarItem`). Sub-rows (depth > 0) render an empty
  spacer of the same width instead, so the name column stays aligned.
- CSS: `.sub-row td:first-child { padding-inline-start: calc(var(--space-4) + var(--depth) * var(--space-6)); }`,
  `.sub-row { background: var(--color-surface); }` (subtle tint like the
  sidebar's indented rows), `.toggle .open { transform: rotate(90deg); }`.
- Empty-state `colspan` unchanged; the toggle lives inside an existing cell.

`getRowId` stays `row => row.id`; tree row ids are unique by construction.

## `AdminTable.svelte`: shared row-menu logic becomes reusable

Move the body of `menuItems(row)` (edit → extra items → row actions → delete/reset)
into `resources/js/plugins/admin/rowMenu.ts`:

```ts
export function recordRowMenu<Row extends AdminRow>(
    recordSet: AdminRecordSet<Row, string, Record<string, unknown>>,
    row: Row,
    __: (key: string, replacements?: Record<string, string>) => string,
    options: { extra?: AdminMenuItem[]; editSystemRows?: boolean } = {}
): AdminMenuItem[];
```

`AdminTable` calls it; behaviour unchanged. The tree component calls it for
server rows (with the servers Record Set) and tool rows (with the tools Record
Set).

## New component: `components/AdminMcpTree.svelte`

Props: `servers`, `tools` (the two Record Sets), `caption`, `expanded`
(bindable `ExpandedState`), `onShowTools?` not needed (the page mutates
`expanded` directly).

- `rows = $derived(buildMcpTree(servers.rows, tools.rows, { lastPage }))`
  where `lastPage = servers.total === undefined || (servers.pagination.pageIndex + 1) * servers.pagination.pageSize >= servers.total`.
- Columns (ids must match the servers Record Set sort keys so `bind:sorting`
  keeps working; `AdminTable`'s `display()` is not reused, every cell is a
  snippet):

  | id | header key | sortable | server row | tool row | builtin row |
  | --- | --- | --- | --- | --- | --- |
  | `server_label` | `admin.fields.name` | yes | label + `Badge variant="secondary"` with `tools_count` | tool name; `title` attribute = description when present | `admin.tool_sources.builtin` + Badge with count |
  | `kind` | `admin.fields.kind` | yes, header filter from the servers `kind` select field | `admin.values.<kind>` (add http/sse/stdio) | `admin.values.<kind>` (function/mcp) | — |
  | `status` | `admin.fields.status` | yes | `admin.values.<status>` | `AdminRowSwitch` bound to `active`, label `admin.fields.active`, `disabled={tools.locked(tool)}`, `onToggle={(on) => tools.update(tool, { active: on })}` | — |
  | `url` | `admin.fields.details` | no | url | mapped capability as the existing `.capability` chip, else — | — |
  | `api_key_set` | `admin.fields.access` | no | `admin.secret_set` / `admin.secret_unset` | access rule title from `tools.content.access_rules` (fallback `admin.tool_access_rules.unavailable.title`) | — |

- `filters`: the `kind` header filter built exactly like `AdminTable` does
  (from the servers Record Set's `kind` select field).
- Actions snippet: server rows → `recordRowMenu(servers, row.server, __, { extra: [] })`;
  tool rows → `recordRowMenu(tools, row.tool, __)`; builtin row → nothing.
  Menu `disabled` uses the owning Record Set's `locked(row)`; `dialogOpen`
  is `servers.dialogOpen || tools.dialogOpen`.
- `DataTable` props: `data={rows}`, `getSubRows={(row) => 'subRows' in row ? row.subRows : undefined}`,
  `bind:expanded`, `expandLabel={(row, open) => __(open ? 'admin.collapse_tools' : 'admin.expand_tools', { name })}`,
  `server` mode bound to the **servers** Record Set (`loading`, `total`,
  `bind:pagination`, `bind:sorting`, `bind:columnFilters`, `onChange={() => servers.load()}`),
  `rowLabel` = server label / tool name / built-in label.
- `loading={servers.loading || tools.loading}`.

## `pages/AdminMcp.svelte`

- Remove: the tools columns declaration (keep a minimal
  `[{ id: 'name' }]` for the Record Set), `useQueryState('mcp_server_id')`,
  the `$effect`s, `selectedServer`, `toolsHeading`, the `.tools` section, the
  second `AdminSearch`/`AdminTable`, the "View tools" row menu item, the
  `mcp_server_id` cell snippet.
- Keep: both Record Sets (tools with `pageSize: 100` initial pagination via
  the options or by assigning `tools.pagination` right after creation),
  `editFields` for tools, server row actions, save/remove, `refresh` chaining.
- `let expanded = $state<ExpandedState>({})`.
- Discover result dialog: keep the tool list; the button `admin.view_tools`
  becomes `admin.show_tools` ("Show tools"): closes the result and sets
  `expanded = { ...(expanded === true ? {} : expanded), [id]: true }`.
- `hint` for truncation (see above).
- Render `<AdminSearch recordSet={servers} />` and
  `<AdminMcpTree {servers} {tools} caption={__('admin.sections.mcp')} bind:expanded />`.

## Translations (flat keys, both `ui_en_US.json` and `ui_de_DE.json`)

| key | en_US | de_DE |
| --- | --- | --- |
| `admin.fields.details` (new) | Details | Details |
| `admin.fields.access` (new) | Access | Zugriff |
| `admin.values.http` (new) | HTTP | HTTP |
| `admin.values.sse` (new) | SSE | SSE |
| `admin.values.stdio` (new) | Stdio | Stdio |
| `admin.expand_tools` (new) | Show tools of :name | Werkzeuge von :name anzeigen |
| `admin.collapse_tools` (new) | Hide tools of :name | Werkzeuge von :name ausblenden |
| `admin.show_tools` (new) | Show tools | Werkzeuge anzeigen |
| `admin.tools_truncated` (new) | :shown of :total tools are shown. Delete unused MCP servers or tools to see all of them. | :shown von :total Werkzeugen werden angezeigt. Löschen Sie ungenutzte MCP-Server oder Werkzeuge, um alle zu sehen. |
| `admin.descriptions.mcp` (change) | Configure server connections, test availability and discover tools. Expand a server to manage its tools; built-in HAWKI tools are listed under their own entry. | Konfigurieren Sie Serververbindungen, prüfen Sie die Verfügbarkeit und ermitteln Sie Werkzeuge. Klappen Sie einen Server auf, um seine Werkzeuge zu verwalten; in HAWKI integrierte Werkzeuge stehen unter einem eigenen Eintrag. |
| `admin.view_tools`, `admin.tools_of`, `admin.show_all_tools`, `admin.fields.mcp_server_id`, `admin.fields.tools_count` | **remove** (no longer referenced) | **remove** |

Keep `admin.tools`, `admin.tool_sources.builtin`, `admin.values.function`,
`admin.values.mcp`.

## Tests and docs

- New `tests/js/admin/mcp-tree.test.ts` (Node, imports only `mcpTree.ts`):
  groups tools under their server, orders servers as given, appends the
  built-in parent only on the last page and only when function tools exist,
  prefixes tool ids, gives tool-less servers an empty `subRows`.
- `tests/js/admin/access.test.ts` untouched.
- `_documentation/200-Configuration/800-Administration.md`: replace the
  round-2 sentences with the tree description (expand a server to see and
  manage its tools; built-in tools under their own entry; at most 100 tools
  are listed).

## Out of scope

- Backend changes (both resources already expose what the tree needs).
- Tool search, tool sorting, moving tools between servers.
- Legacy UI (`public/js`).
