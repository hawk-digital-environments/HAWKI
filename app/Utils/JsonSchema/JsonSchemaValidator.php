<?php
declare(strict_types=1);


namespace App\Utils\JsonSchema;


use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\ObjectSchema;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates arbitrary data against a JSON schema, returning structured error details on failure.
 *
 * Accepts schemas in two formats:
 * - As an `array<string, Type>` of Laravel {@see JsonSchemaTypeFactory} type objects — the format
 *   returned by {@see Tool::schema()}. These are automatically wrapped into a top-level object schema
 *   before validation.
 * - As a raw JSON Schema string for cases where you already hold a serialised schema.
 *
 * Returns `null` when data is valid and an array of structured error details when it is not.
 * Internally delegates to `opis/json-schema`; that package must be installed.
 *
 * Usage:
 * ```php
 * $validator = new JsonSchemaValidator();
 * $factory   = new JsonSchemaTypeFactory();
 *
 * // From a Tool::schema() array
 * $schema = [
 *     'city' => $factory->string()->required(),
 *     'unit' => $factory->string()->enum(['celsius', 'fahrenheit']),
 * ];
 *
 * $errors = $validator->validate($schema, ['city' => 'Berlin']);        // null  — valid
 * $errors = $validator->validate($schema, ['city' => 'Berlin', 'unit' => 'kelvin']); // array — invalid
 *
 * // From a raw JSON Schema string
 * $errors = $validator->validate(
 *     '{"type":"object","properties":{"name":{"type":"string"}},"required":["name"]}',
 *     ['name' => 'Bob'],
 * ); // null — valid
 * ```
 */
class JsonSchemaValidator
{
    /**
     * Validates the given data against the provided JSON schema.
     *
     * The schema may be supplied as either:
     * - An `array<string, Type>` of Laravel JsonSchema Type objects, as returned by {@see Tool::schema()}.
     *   The array is automatically wrapped in a top-level object schema before validation so that each
     *   key maps to a property of the root object.
     * - A raw JSON string containing a complete JSON Schema definition, useful when you already hold a
     *   serialised schema (e.g. retrieved from a database or built manually).
     *
     * PHP arrays are converted to plain objects before being passed to the validator because
     * opis/json-schema expects the same structure that `json_decode` produces by default.
     *
     * @param array<string, Type>|string $schema
     */
    public function validate(array|string $schema, array $data): array|string
    {
        $json = $this->schemaToJson($schema);
        $data = $this->coerceTypes($data, $json);
        $d = $this->dataToObject($data);
        $result = (new Validator())->validate($d, $json);

        if ($result->isValid()) {
            return $this->objectToData($d);
        }

        return (new ErrorFormatter())->formatErrorMessage($result->error());
    }

    /**
     * Absorbs the type sloppiness small models produce in tool arguments —
     * numeric strings for integer/number fields, quoted booleans, numbers
     * for string fields, explicit nulls — by coercing each top-level value
     * to the type its schema property declares. Values that do not convert
     * cleanly pass through untouched and still fail strict validation, so
     * the schema contract itself is not loosened.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function coerceTypes(array $data, string $schemaJson): array
    {
        $schema = json_decode($schemaJson, true);
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        foreach ($data as $key => $value) {
            $type = $properties[$key]['type'] ?? null;

            if ($value === null) {
                // Nullable properties (incl. union types with null) keep
                // their null; for everything else a null carries no
                // argument, and dropping it surfaces a missing-required
                // error instead of a confusing type error.
                if (!\is_array($type) || !\in_array('null', $type, true)) {
                    unset($data[$key]);
                }

                continue;
            }

            if (is_string($type)) {
                $data[$key] = match ($type) {
                    'integer' => $this->coerceInteger($value),
                    'number' => $this->coerceNumber($value),
                    'boolean' => $this->coerceBoolean($value),
                    'string' => $this->coerceString($value),
                    default => $value,
                };
            }
        }

        return $data;
    }

    private function coerceInteger(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if ((is_string($value) && is_numeric($value)) || is_float($value)) {
            $number = (float) $value;

            return $number === (float) (int) $number ? (int) $number : $value;
        }

        return $value;
    }

    private function coerceNumber(mixed $value): mixed
    {
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    private function coerceBoolean(mixed $value): mixed
    {
        if (is_string($value)) {
            return match (strtolower($value)) {
                'true' => true,
                'false' => false,
                default => $value,
            };
        }

        if ($value === 1 || $value === 0) {
            return (bool) $value;
        }

        return $value;
    }

    private function coerceString(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $value;
    }

    private function dataToObject(array $data): object
    {
        return (object)json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    private function objectToData(object $object): array
    {
        return json_decode(json_encode($object, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    private function schemaToJson(array|string $schema): string
    {
        if (is_string($schema)) {
            return $schema;
        }

        return json_encode((new ObjectSchema($schema))->toSchema(), JSON_THROW_ON_ERROR);
    }
}
