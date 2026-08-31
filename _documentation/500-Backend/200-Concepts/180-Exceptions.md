# Exceptions

One dedicated exception class per error condition. A reader who catches the exception should know exactly what went wrong from the class name.

## How to throw

Each domain has its own exceptions under `{Domain}/Exceptions/`. A domain marker interface (`{Domain}ExceptionInterface extends \Throwable`) that all domain exceptions implement lets a caller catch the whole domain in one block:

```php
namespace App\Services\Ai\Exceptions;

interface AiExceptionInterface extends \Throwable {}
```

Construction goes through static factory methods with speaking error messages:

```php
namespace App\Services\Ai\Exceptions;

class ModelNotFoundException extends \RuntimeException implements AiExceptionInterface
{
    public static function forId(string $id): self
    {
        return new self("AI model with id '{$id}' was not found.");
    }
}
```

The caller writes:

```php
try {
    $model = $this->models->findOrFail($id);
} catch (AiExceptionInterface $e) {
    // domain-level handling
}
```

## Rules

- One exception class per error condition. Do not reuse `RuntimeException` with a message switch.
- Every domain exception implements the domain's `ExceptionInterface`.
- Static factory methods, not `new`. The factory builds the speaking message.
- Throw at the boundary where the error is detected; let it bubble to a service boundary that can decide.

## Who logs?

| Decision                  | Rule                                                                                     |
|---------------------------|------------------------------------------------------------------------------------------|
| You swallow the exception | You **must** log — no one else will                                                      |
| You re-throw or convert   | Log only the contextual enrichment you add; the next catch site logs its own decision    |
| Unhandled                 | Laravel catches and logs automatically — catch defensively at service boundaries instead |

Never double-log. Pass the full exception object in the PSR log context: `['exception' => $e]`.
