# Events & Listeners

Domain events let a service announce that something happened without coupling to whoever cares. Listeners react without the service knowing they exist.

## Where events and listeners live

- Events live in `app/Services/{Domain}/Events/`.
- Listeners live in `app/Services/{Domain}/Listeners/` and are **auto-discovered** from that path — no manual registration needed. If you find a global `app/Events/` or `app/Listeners/` root, it is a legacy artifact.

## Naming

- Past tense for things that happened: `MessageSentEvent`, `RoomCreatedEvent`.
- Present progressive for things in progress: `CheckingHealthEvent`.
- `Before` prefix for things about to happen: `BeforeCreatingRoomEvent`.
- Always add the `Event` suffix.

## Dispatching

```php
event(new MessageSentEvent($message));
// or
MessageSentEvent::dispatch($message);
```

Listeners receive the event via their `handle` method and are resolved through the container, so they can inject dependencies via constructor.

## Filter events — `DispatchableFilter`

Filter events are a synchronous hook mechanism that lets listeners **modify** data in a pipeline without subclassing. They are the primary plugin interception point for modifying data in HAWKI's pipelines.

```php
$isAllowed = ModelPermissionFilterEvent::dispatch($user, $model)->isAllowed();
```

Filter events use `DispatchableFilter` instead of `Dispatchable`, expose controlled getters/setters, and are never queued or broadcast. Add a listener to any `...FilterEvent` class to intercept the pipeline at that point:

```php
class MyModelPermissionListener
{
    public function handle(ModelPermissionFilterEvent $event): void
    {
        if ($this->policy->denies($event->getUser(), $event->getModel())) {
            $event->setAllowed(false);
        }
    }
}
```

The full list of live extension points, including the available filter events, is in [Extending HAWKI](./220-Extending-HAWKI.md).

## Dragons

- Filter events are synchronous. Do not do slow work in a filter listener — it blocks every request that hits that pipeline.
- Listeners are auto-discovered only from `app/Services/*/Listeners/`. A listener placed anywhere else is silently ignored.
