# Model Casts

How model columns hydrate to typed PHP values and persist back. HAWKI has two separate tools for this — they look similar but solve different problems.

## Two tools, not two layers

- **Eloquent casts** (`app/Casts/`) extend Laravel's well-known `$casts` pattern. They are specific to Eloquent models: the cast reads from and writes to a model column. See [Eloquent Casts](./100-Eloquent-Casts.md).

- **`AbstractCastableObject`** (`app/Utils/Casts/`) is a different tool that works completely without models. It hydrates typed PHP objects from flat string maps (database rows, config entries, environment files) via reflection. It is the base for `AbstractConfig` (see [Config Blocks](../200-Config-Blocks.md)) and the planned database-backed configuration system. See [Castable Objects](./200-Castable-Objects.md).

Do not conflate them. An Eloquent cast is a Laravel pattern attached to a model column; a castable object is a standalone serialisation tool that can be used anywhere a flat string map needs to become a typed object.
