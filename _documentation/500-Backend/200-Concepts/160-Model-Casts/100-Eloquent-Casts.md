# Eloquent Casts

HAWKI's custom Eloquent cast classes in `app/Casts/`. These extend Laravel's `$casts` pattern — they are specific to Eloquent models and read from / write to model columns.

## The cast set

| Cast                         | Purpose                                                                                           |
|------------------------------|---------------------------------------------------------------------------------------------------|
| `AsInstance`                 | Generic cast for any class implementing `CastableInstanceInterface` (`fromArray()` / `toArray()`). Stores as JSON. Used widely for structured value objects on models. |
| `AsLocale`                   | Casts a DB string to a `Locale` value object via `LocaleService::getMostLikelyLocale()` |
| `AsAsymmetricPublicKeyCast`  | Transparent asymmetric public key encryption/decryption on a model attribute                      |
| `AsHybridCryptoValueCast`    | Transparent hybrid encryption/decryption (random AES key + asymmetric wrapping)                    |
| `AsSymmetricCryptoValueCast` | Transparent symmetric AES-256-GCM encryption/decryption                                            |

The crypto casts are the bridge between the model layer and the encryption system. See [Encryption](../../400-Domains/400-Encryption/410-Encryption.md) for the three tiers and the wire formats.

## `AsInstance` — structured value objects on models

`AsInstance` is the workhorse cast for typed value objects. It serialises any class implementing `CastableInstanceInterface` to and from a JSON string in the database.

### Why use it

When a model column holds structured data (a list of IO methods, a set of sampling parameters, a capability map), storing it as a typed value object gives you:
- IDE autocomplete for the object's properties and methods
- Validation and defaults at the value object boundary
- No more `$model->attributes['some_key']` array access

### How to write a value object for `AsInstance`

Implement `CastableInstanceInterface` — two methods:

```php
use App\Casts\Contracts\CastableInstanceInterface;

class ModelIoMethods implements CastableInstanceInterface
{
    public function __construct(
        public readonly array $input = [],
        public readonly array $output = [],
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            input: $data['input'] ?? [],
            output: $data['output'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'input' => $this->input,
            'output' => $this->output,
        ];
    }
}
```

`fromArray()` hydrates from the decoded JSON; `toArray()` serialises back. The database column stores the JSON-encoded array.

### How to apply it in a model

You **cannot** use `AsInstance` in the `$casts` property directly — it needs a class argument, so it must go in the `casts()` method (Laravel 11+ style):

```php
use App\Casts\AsInstance;

class AiModel extends Model
{
    protected function casts(): array
    {
        return [
            'input'  => AsInstance::of(ModelIoMethods::class),
            'parameters' => AsInstance::of(AiModelParameters::class),
        ];
    }
}
```

`AsInstance::of()` returns the cast string with the class name base64-encoded (to survive Laravel's colon-based cast-string parsing). Do not build the string manually.

For dynamic resolution (when the target class depends on the model's state), pass a closure:

```php
'io_list' => AsInstance::of(function ($model, $key, $value, $attributes) {
    return $model->is_special ? SpecialModelIoMethods::class : ModelIoMethods::class;
}),
```

### How the migration looks

The database column is a standard `json` column:

```php
Schema::create('ai_models', function (Blueprint $table) {
    $table->json('input')->nullable();
    $table->json('parameters')->nullable();
});
```

`null` values are treated as empty arrays (MySQL's default for `json` columns).

## The other casts

- **`AsLocale`** — a one-line cast: `'locale' => AsLocale::class` in `$casts`. Resolves the DB string to a `Locale` value object via `LocaleService`.
- **Crypto casts** — applied the same way as any cast: `'public_key' => AsAsymmetricPublicKeyCast::class`. The cast handles encryption/decryption transparently; the service reads a typed value object out of the model. See [Encryption](../../400-Domains/400-Encryption/410-Encryption.md) for the wire formats.

## Source

- `app/Casts/` — all cast classes
- `app/Casts/Contracts/CastableInstanceInterface.php`
