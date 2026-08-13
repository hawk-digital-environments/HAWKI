# Concepts

The hub for "how do I use pattern X in HAWKI." One page per pattern; every domain page links here instead of redefining.

Each page follows the same shape: what it is, when to use it, how to use it (real, copyable code), why it exists, and the dragons to watch for.

## I want to…

| I want to…                                   | Read                                                                          |
|----------------------------------------------|-------------------------------------------------------------------------------|
| Understand the layer rules and DDD-light     | [Layers & Domains](./100-Layers-and-Domains.md)                              |
| Inject dependencies into a service           | [Dependency Injection](./110-Dependency-Injection/index.md)                        |
| Know who the current caller is                | [Request Contexts](./120-Request-Contexts.md)                                |
| Implement a repository                        | [Repositories](./130-Repositories.md)                                          |
| Disable an Eloquent scope for one query       | [Contextual Scopes](./140-Contextual-Scopes.md)                               |
| Build a value object                          | [Value Objects](./150-Value-Objects.md)                                       |
| Cast a model column to a typed object         | [Eloquent Casts](./160-Model-Casts/100-Eloquent-Casts.md)                                    |
| Serialise a typed object to/from a string map  | [Castable Objects](./160-Model-Casts/200-Castable-Objects.md)                                |
| Dispatch a domain event or filter event       | [Events & Listeners](./170-Events-and-Listeners.md)                          |
| Throw a domain exception                      | [Exceptions](./180-Exceptions.md)                                             |
| Resolve a dependency in an API Resource       | [ServiceLocator](./110-Dependency-Injection/100-ServiceLocator.md)                                    |
| Add a public config block                     | [Config Blocks](./200-Config-Blocks.md)                                       |
| Mark a class as stable for plugins to depend on | [API Stability](./210-API-Stability.md)                                   |
| Extend HAWKI without touching core            | [Extending HAWKI](./220-Extending-HAWKI.md)                                  |
