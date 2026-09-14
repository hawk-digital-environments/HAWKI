# HAWKI — Domain Glossary

Terms used in the new Svelte frontend. Implementation-free: this describes
what things *are*, not how they are built.

## Platform

- **Plugin** — A deliverable unit of functionality that is installed into HAWKI
  as a whole. `core` is the plugin that ships with HAWKI itself. Plugins own
  Modules and Stores.
- **Module** — One logical feature a user can switch to (e.g. *Chat*). A module
  has a title, an icon, its own pages and its own sidebar. The Module Selector
  in the sidebar lists all Modules.
- **Store** — A shared, reactive source of state that outlives any single
  screen. Stores are registered by Plugins and looked up by name.

## Search

- **Search Palette** (de: *Suchleiste*) — The app-wide "Spotlight"-style dialog
  opened with ⌘K / Ctrl+K or the sidebar search action. It shows Search Groups
  and lets the user pick one Search Item. Picking closes the palette; what
  happens afterwards is decided by the Item, not by the palette.
- **Search Group** — A named, ordered collection of Search Items contributed by
  one Plugin or Module (e.g. *Conversations* contributed by Chat). Groups are
  shown with a heading in registration order. A Group is a *live* source: its
  Items reflect the current state of whatever it represents, not a snapshot
  taken when it was contributed. The registry Groups are contributed to is
  part of the platform itself, not of any Plugin, so the palette exists even
  when no Plugin contributes anything.
- **Search Item** — One selectable row in the palette. Has a stable identity,
  a title, an optional icon, optional keywords, and an action. Two Items may
  share a title (two chats named "Test") but never an identity.
- **Keywords** — Additional terms an Item is findable by, beyond its title.
  Not shown to the user.
- **Query** — What the user has typed into the palette. An Item *matches* the
  Query when the Query is contained in its title or in one of its keywords,
  case-insensitively. An empty Query matches everything.

## Chat

- **Conversation** (de: *Unterhaltung*) — One chat thread of the user, identified
  by a slug, with a user-given or generated name. The Chat Module contributes
  Conversations to the Search Palette as the *Conversations* group, newest first.
