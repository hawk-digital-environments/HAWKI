# Assistants Plugin

Frontend plugin providing the assistant store/dashboard and the advanced
assistant builder. Entry point: `assistants.plugin.ts`, which registers the
plugin's resource schemas, the assistant options store, and the two modules
below.

## Modules

| Module    | Class            | File                                     | Description                                                        |
|-----------|------------------|------------------------------------------|--------------------------------------------------------------------|
| Dashboard | `DashboardModule`| `modules/dashboard/DashboardModule.ts`   | Store, drafts, favourites, shared and assistant detail pages       |
| Builder   | `BuilderModule`  | `modules/builder/BuilderModule.ts`       | Advanced assistant editing sections, wrapped in a shared layout     |

Both modules set `pluginNameInRoutes = true`, so their routes are prefixed
with `/assistants/<module>` (see `kernel/routing/routeInflection.ts`).

## Folder structure

Follows the core plugin convention (`plugins/core/modules/chat/`): components
used by a single module live inside that module; only genuinely shared
components stay at plugin level.

```
plugins/assistants/
├── actions/                  # plugin-wide reusable actions (dragDrop)
├── api/                      # clients, schemas, errors
├── components/               # shared across modules
│   ├── avatarBuilder/        #   shared (dashboard + builder)
│   ├── report/               #   shared (dashboard + builder)
│   ├── status/               #   StatusPill — semantic status badge
│   ├── tags/                 #   Tag + AddButton (generic tag chip + add input)
│   └── closeBtn/, dragDropOverlay/, emojiPicker/, inputError/,
│       itemList/, radioSwitch/, select/, textInputs/, toggle/
│                             #   generic UI primitives
├── presets/                  # shared presets (avatar backgrounds)
├── stores/                   # app-wide stores (AssistantOptionsStore)
├── types/                    # shared types
├── utils/                    # shared utils (proximityHover)
└── modules/
    ├── dashboard/
    │   ├── DashboardModule.ts
    │   ├── components/       # assistantBrowser/, categoryBar/, searchbar/,
    │   │                     # versionTimeline/, favButton/, feedbackPanel/
    │   ├── contexts/         # AssistantListContext
    │   └── pages/            # store/, drafts/, favourites/, shared/, detail/
    └── builder/
        ├── BuilderModule.ts
        ├── components/       # BuilderInput, FileUpload, KnowledgeBases,
        │                     # ReleaseStage*, RiskStatus, modelSelector/,
        │                     # tags/ (TagInput), aiToolComponents/, chat/Chatbox/
        ├── contexts/         # BuilderContext, validation
        └── pages/            # advanced/ sections + layout
```

Conventions:

- Components used by exactly one module live in that module's `components/`;
  only components shared by dashboard **and** builder stay at plugin level.
- Import direction: module code may import plugin-level shared code — never
  the other way around. Modules never import from each other.
- Use the `$plugins/assistants/...` alias (not `$lib/plugins/assistants/...`).
- Register pages via `lazyRoute` so they stay out of the initial bundle.

## Routes — Dashboard (`modules/dashboard/DashboardModule.ts`)

| Path                              | Route name                     |
|-----------------------------------|--------------------------------|
| `/assistants/dashboard/store`     | `assistants.dashboard.store`    |
| `/assistants/dashboard/drafts`    | `assistants.dashboard.drafts`   |
| `/assistants/dashboard/favourites`| `assistants.dashboard.favourites` |
| `/assistants/dashboard/shared`    | `assistants.dashboard.shared`   |
| `/assistants/dashboard/:id`       | `assistants.dashboard.details` |

## Routes — Builder (`modules/builder/BuilderModule.ts`)

All routes live under a shared layout (`pages/advanced/layout.svelte`) that
creates and provides the `BuilderContext` once and keeps it mounted while
navigating between sections.

| Path                                     | Route name                   |
|------------------------------------------|------------------------------|
| `/assistants/builder/advanced`           | `assistants.builder.index`    |
| `/assistants/builder/advanced/general`   | `assistants.builder.general`  |
| `/assistants/builder/advanced/behaviour` | `assistants.builder.behaviour`|
| `/assistants/builder/advanced/knowledge` | `assistants.builder.knowledge`|
| `/assistants/builder/advanced/model`     | `assistants.builder.model`    |
| `/assistants/builder/advanced/test`      | `assistants.builder.test`     |
| `/assistants/builder/advanced/publish`   | `assistants.builder.publish`  |

## Notes

- `/assistants/builder/advanced` redirects to
  `/assistants/builder/advanced/general` via its page's `loadData`
  (`pages/advanced/index.svelte`).
