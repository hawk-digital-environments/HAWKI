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
        $d = $this->dataToObject($data);
        $result = (new Validator())->validate($d, $this->schemaToJson($schema));

        if ($result->isValid()) {
            return $this->objectToData($d);
        }

        return (new ErrorFormatter())->formatErrorMessage($result->error());
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
